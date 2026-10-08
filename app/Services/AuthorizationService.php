<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Authorization;
use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AuthorizationService
{
    public function process(array $data): array
    {
        $existingAuthorization = Authorization::query()->where('network_id', $data['id'])->first();

        if ($existingAuthorization !== null) {
            return $this->responseFromAuthorization($existingAuthorization);
        }

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

            return ['decision' => 'approved'];
        });
    }

    private function decline(array $data, string $month, ?Card $card, string $reason): array
    {
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

        return [
            'decision' => 'declined',
            'reason' => $reason,
        ];
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
