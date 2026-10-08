<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Authorization;
use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\PurchaseIssue;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class AuthorizationService
{
    public function process(array $data): array
    {
        $existingAuthorization = Authorization::query()
            ->where('network_id', $data['id'])
            ->first();

        if ($existingAuthorization !== null) {
            return $this->responseFromAuthorization($existingAuthorization);
        }

        try {
            return DB::transaction(function () use ($data): array {
                $month = CarbonImmutable::createFromFormat(
                    'Y-m-d\TH:i:s\Z',
                    $data['occurred_at'],
                    'UTC'
                )
                    ->setTimezone('America/Sao_Paulo')
                    ->format('Y-m');

                $card = Card::query()->where('card_token', $data['card_token'])->first();

                if ($card === null) {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: null,
                        reason: 'card_not_found',
                    );
                }

                if ($card->status === 'blocked') {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: $card,
                        reason: 'card_blocked',
                    );
                }

                if (in_array($data['mcc'], $card->blocked_mccs ?? [], true)) {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: $card,
                        reason: 'mcc_blocked',
                    );
                }

                if ($card->purchase_limit_cents !== null && $data['amount_cents'] > $card->purchase_limit_cents) {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: $card,
                        reason: 'amount_over_purchase_limit',
                    );
                }

                $company = Company::query()->whereKey($card->company_id)->lockForUpdate()->firstOrFail();

                $monthBalance = CardMonthBalance::query()->firstOrCreate(
                    [
                        'card_id' => $card->id,
                        'month' => $month,
                    ],
                    [
                        'limit_cents' => $card->monthly_limit_cents,
                        'limit_remaining_cents' => $card->monthly_limit_cents,
                    ],
                );

                $monthBalance = CardMonthBalance::query()->whereKey($monthBalance->id)->lockForUpdate()->firstOrFail();

                if ($data['amount_cents'] > $monthBalance->limit_remaining_cents) {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: $card,
                        reason: 'monthly_limit_exceeded'
                    );
                }

                $companyAvailable = $company->balance_cents - $company->reserved_cents;

                if ($data['amount_cents'] > $companyAvailable) {
                    return $this->decline(
                        data: $data,
                        month: $month,
                        card: $card,
                        reason: 'insufficient_funds'
                    );
                }

                $purchase = Purchase::query()
                    ->where('authorization_network_id', $data['id'])
                    ->lockForUpdate()
                    ->first();

                if ($purchase === null) {
                    $purchase = Purchase::query()->create([
                        'authorization_network_id' => $data['id'],
                        'card_id' => $card->id,
                        'month' => $month,
                        'status' => 'authorized',
                        'authorized_amount_cents' => $data['amount_cents'],
                        'captured_amount_cents' => 0,
                        'reserved_amount_cents' => $data['amount_cents'],
                        'has_final_capture' => false,
                        'has_cancellation' => false,
                    ]);
                } else {
                    $purchase->update([
                        'card_id' => $card->id,
                        'month' => $month,
                        'status' => 'authorized',
                        'authorized_amount_cents' => $data['amount_cents'],
                        'captured_amount_cents' => 0,
                        'reserved_amount_cents' => $data['amount_cents'],
                    ]);
                }

                Authorization::query()->create([
                    'network_id' => $data['id'],
                    'purchase_id' => $purchase->id,
                    'card_id' => $card->id,
                    'card_token' => $data['card_token'],
                    'amount_cents' => $data['amount_cents'],
                    'currency' => $data['currency'],
                    'mcc' => $data['mcc'],
                    'merchant' => $data['merchant'],
                    'decision' => 'approved',
                    'reason' => null,
                    'occurred_at' => $data['occurred_at'],
                ]);

                $monthBalance->decrement('limit_remaining_cents', $data['amount_cents']);

                $company->increment('reserved_cents', $data['amount_cents']);

                Transaction::query()->create([
                    'company_id' => $company->id,
                    'purchase_id' => $purchase->id,
                    'card_id' => $card->id,
                    'month' => $month,
                    'reference' => $data['id'],
                    'type' => 'authorization_hold',
                    'card_limit_delta_cents' => -$data['amount_cents'],
                    'company_balance_delta_cents' => 0,
                    'company_reserved_delta_cents' => $data['amount_cents'],
                    'occurred_at' => $data['occurred_at'],
                ]);

                $this->reconcilePendingCaptures(
                    purchase: $purchase,
                    company: $company,
                    monthBalance: $monthBalance,
                    mcc: $data['mcc'],
                );

                $this->reconcilePendingCancellation(
                    purchase: $purchase,
                    company: $company,
                    monthBalance: $monthBalance,
                );

                return ['decision' => 'approved'];
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            $authorization = Authorization::query()
                ->where('network_id', $data['id'])
                ->first();

            if ($authorization === null) {
                throw $exception;
            }

            return $this->responseFromAuthorization($authorization);
        }
    }

    private function decline(array $data, string $month, ?Card $card, string $reason): array
    {
        $purchase = Purchase::query()
            ->where('authorization_network_id', $data['id'])
            ->lockForUpdate()
            ->first();

        if ($purchase === null) {
            $purchase = Purchase::query()->create([
                'authorization_network_id' => $data['id'],
                'card_id' => $card?->id,
                'month' => $month,
                'status' => 'declined',
                'authorized_amount_cents' => $data['amount_cents'],
                'captured_amount_cents' => 0,
                'reserved_amount_cents' => 0,
                'has_final_capture' => false,
                'has_cancellation' => false,
            ]);
        } else {
            $purchase->update([
                'card_id' => $card?->id,
                'month' => $month,
                'status' => 'declined',
                'authorized_amount_cents' => $data['amount_cents'],
                'reserved_amount_cents' => 0,
            ]);
        }

        Authorization::query()->create([
            'network_id' => $data['id'],
            'purchase_id' => $purchase->id,
            'card_id' => $card?->id,
            'card_token' => $data['card_token'],
            'amount_cents' => $data['amount_cents'],
            'currency' => $data['currency'],
            'mcc' => $data['mcc'],
            'merchant' => $data['merchant'],
            'decision' => 'declined',
            'reason' => $reason,
            'occurred_at' => $data['occurred_at'],
        ]);

        $this->reconcileDeclinedPendingCaptures(
            purchase: $purchase,
            card: $card,
            month: $month,
        );

        return [
            'decision' => 'declined',
            'reason' => $reason,
        ];
    }

    private function reconcilePendingCaptures(
        Purchase $purchase,
        Company $company,
        CardMonthBalance $monthBalance,
        string $mcc,
    ): void {
        $captures = $purchase->captures()
            ->orderBy('sequence')
            ->get();

        foreach ($captures as $capture) {
            $reservedBefore = $purchase->reserved_amount_cents;
            $capturedBefore = $purchase->captured_amount_cents;

            $capturedAfter = $capturedBefore + $capture->amount_cents;

            $specialMccs = ['5812', '7011', '7512'];

            $overExpected = in_array($mcc, $specialMccs, true)
                ? ($capturedAfter * 100) > ($purchase->authorized_amount_cents * 120)
                : $capturedAfter > $purchase->authorized_amount_cents;

            if ($purchase->has_final_capture || $capture->final) {
                $reservedAfter = 0;
            } else {
                $reservedAfter = max(
                    $purchase->authorized_amount_cents - $capturedAfter,
                    0,
                );
            }

            $previousConsumed = $capturedBefore + $reservedBefore;
            $newConsumed = $capturedAfter + $reservedAfter;

            $cardLimitDelta = $previousConsumed - $newConsumed;
            $companyReservedDelta = $reservedAfter - $reservedBefore;

            if ($overExpected) {
                PurchaseIssue::query()->firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'code' => 'overcapture_exceeded',
                    ],
                    [
                        'related_network_id' => $capture->network_id,
                        'detected_at' => now(),
                    ],
                );
            }

            $purchase->update([
                'captured_amount_cents' => $capturedAfter,
                'reserved_amount_cents' => $reservedAfter,
                'has_final_capture' => $purchase->has_final_capture || $capture->final,
                'status' => $purchase->has_cancellation
                    ? 'canceled'
                    : ($capture->final ? 'settled' : 'partially_captured'),
            ]);

            if ($cardLimitDelta !== 0) {
                $monthBalance->increment(
                    'limit_remaining_cents',
                    $cardLimitDelta,
                );
            }

            $company->decrement(
                'balance_cents',
                $capture->amount_cents,
            );

            if ($companyReservedDelta > 0) {
                $company->increment(
                    'reserved_cents',
                    $companyReservedDelta,
                );
            } elseif ($companyReservedDelta < 0) {
                $company->decrement(
                    'reserved_cents',
                    abs($companyReservedDelta),
                );
            }

            Transaction::query()->create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'card_id' => $purchase->card_id,
                'month' => $purchase->month,
                'reference' => $capture->network_id,
                'type' => 'capture_settlement',
                'card_limit_delta_cents' => $cardLimitDelta,
                'company_balance_delta_cents' => -$capture->amount_cents,
                'company_reserved_delta_cents' => $companyReservedDelta,
                'occurred_at' => $capture->occurred_at,
            ]);
        }
    }

    private function reconcileDeclinedPendingCaptures(
        Purchase $purchase,
        ?Card $card,
        string $month,
    ): void {
        $captures = $purchase->captures()
            ->orderBy('sequence')
            ->get();

        if ($captures->isEmpty()) {
            return;
        }

        $company = $card !== null
            ? Company::query()
                ->whereKey($card->company_id)
                ->lockForUpdate()
                ->firstOrFail()
            : Company::query()
                ->lockForUpdate()
                ->firstOrFail();

        $monthBalance = null;

        if ($card !== null) {
            $monthBalance = CardMonthBalance::query()->firstOrCreate(
                [
                    'card_id' => $card->id,
                    'month' => $month,
                ],
                [
                    'limit_cents' => $card->monthly_limit_cents,
                    'limit_remaining_cents' => $card->monthly_limit_cents,
                ],
            );

            $monthBalance = CardMonthBalance::query()
                ->whereKey($monthBalance->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        foreach ($captures as $capture) {
            $cardLimitDelta = $card !== null
                ? -$capture->amount_cents
                : 0;

            if ($monthBalance !== null) {
                $monthBalance->increment(
                    'limit_remaining_cents',
                    $cardLimitDelta,
                );
            }

            $company->decrement(
                'balance_cents',
                $capture->amount_cents,
            );

            PurchaseIssue::query()->firstOrCreate(
                [
                    'purchase_id' => $purchase->id,
                    'code' => 'capture_for_declined_authorization',
                ],
                [
                    'related_network_id' => $capture->network_id,
                    'detected_at' => now(),
                ],
            );

            Transaction::query()->create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'card_id' => $card?->id,
                'month' => $month,
                'reference' => $capture->network_id,
                'type' => 'capture_settlement',
                'card_limit_delta_cents' => $cardLimitDelta,
                'company_balance_delta_cents' => -$capture->amount_cents,
                'company_reserved_delta_cents' => 0,
                'occurred_at' => $capture->occurred_at,
            ]);

            $purchase->increment(
                'captured_amount_cents',
                $capture->amount_cents,
            );
        }
    }

    private function reconcilePendingCancellation(
        Purchase $purchase,
        Company $company,
        CardMonthBalance $monthBalance,
    ): void {
        if (! $purchase->has_cancellation) {
            return;
        }

        $cancellation = $purchase->cancellation;

        if ($cancellation === null) {
            return;
        }

        $reservedBefore = $purchase->reserved_amount_cents;

        if ($reservedBefore > 0) {
            $monthBalance->increment(
                'limit_remaining_cents',
                $reservedBefore,
            );

            $company->decrement(
                'reserved_cents',
                $reservedBefore,
            );

            Transaction::query()->create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'card_id' => $purchase->card_id,
                'month' => $purchase->month,
                'reference' => $cancellation->network_id,
                'type' => 'cancellation_release',
                'card_limit_delta_cents' => $reservedBefore,
                'company_balance_delta_cents' => 0,
                'company_reserved_delta_cents' => -$reservedBefore,
                'occurred_at' => $cancellation->occurred_at,
            ]);
        }

        $purchase->update([
            'reserved_amount_cents' => 0,
            'status' => 'canceled',
        ]);
    }

    private function responseFromAuthorization(Authorization $authorization): array
    {
        if ($authorization->decision === 'approved') {
            return ['decision' => 'approved'];
        }

        return [
            'decision' => 'declined',
            'reason' => $authorization->reason,
        ];
    }
}
