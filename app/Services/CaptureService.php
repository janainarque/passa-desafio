<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Capture;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\PurchaseIssue;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type CaptureData array{
 *     id: string,
 *     authorization_id: string,
 *     sequence: int,
 *     amount_cents: int,
 *     currency: string,
 *     final: bool,
 *     occurred_at: string
 * }
 * @phpstan-type EventResponse array{
 *     received: true,
 *     pending?: true
 * }
 */
final class CaptureService
{
    /**
     * @param  CaptureData  $data
     * @return EventResponse
     */
    public function process(array $data): array
    {
        $existingCapture = Capture::query()
            ->where('network_id', $data['id'])
            ->first();

        if ($existingCapture !== null) {
            return ['received' => true];
        }

        return DB::transaction(function () use ($data): array {
            $purchase = Purchase::query()
                ->where('authorization_network_id', $data['authorization_id'])
                ->lockForUpdate()
                ->first();

            if ($purchase === null) {
                $purchase = Purchase::query()->create([
                    'authorization_network_id' => $data['authorization_id'],
                    'card_id' => null,
                    'month' => null,
                    'status' => 'pending_authorization',
                    'authorized_amount_cents' => null,
                    'captured_amount_cents' => 0,
                    'reserved_amount_cents' => 0,
                    'has_final_capture' => false,
                    'has_cancellation' => false,
                ]);

                Capture::query()->create([
                    'network_id' => $data['id'],
                    'purchase_id' => $purchase->id,
                    'sequence' => $data['sequence'],
                    'amount_cents' => $data['amount_cents'],
                    'currency' => $data['currency'],
                    'final' => $data['final'],
                    'occurred_at' => $data['occurred_at'],
                ]);

                return [
                    'received' => true,
                    'pending' => true,
                ];
            }

            $captureAfterCancellation = $purchase->has_cancellation;

            $existingSequence = Capture::query()
                ->where('purchase_id', $purchase->id)
                ->where('sequence', $data['sequence'])
                ->first();

            if ($existingSequence !== null) {
                return ['received' => true];
            }

            if ($purchase->card_id === null) {
                Capture::query()->create([
                    'network_id' => $data['id'],
                    'purchase_id' => $purchase->id,
                    'sequence' => $data['sequence'],
                    'amount_cents' => $data['amount_cents'],
                    'currency' => $data['currency'],
                    'final' => $data['final'],
                    'occurred_at' => $data['occurred_at'],
                ]);

                return [
                    'received' => true,
                    'pending' => true,
                ];
            }

            $company = Company::query()
                ->whereHas('cards', fn ($query) => $query->whereKey($purchase->card_id))
                ->lockForUpdate()
                ->firstOrFail();

            $monthBalance = CardMonthBalance::query()
                ->where('card_id', $purchase->card_id)
                ->where('month', $purchase->month)
                ->lockForUpdate()
                ->first();

            if ($monthBalance === null) {
                $card = $purchase->card;

                $monthBalance = CardMonthBalance::query()->create([
                    'card_id' => $purchase->card_id,
                    'month' => $purchase->month,
                    'limit_cents' => $card->monthly_limit_cents,
                    'limit_remaining_cents' => $card->monthly_limit_cents,
                ]);
            }

            $reservedBefore = $purchase->reserved_amount_cents;
            $capturedBefore = $purchase->captured_amount_cents;

            $capturedAfter = $capturedBefore + $data['amount_cents'];

            $authorization = $purchase->authorization;

            $authorizationWasDeclined = $authorization->decision === 'declined';

            $specialMccs = ['5812', '7011', '7512'];

            $overExpected = in_array($authorization->mcc, $specialMccs, true)
                ? ($capturedAfter * 100) > ($purchase->authorized_amount_cents * 120)
                : $capturedAfter > $purchase->authorized_amount_cents;

            if (
                $authorizationWasDeclined
                || $purchase->has_cancellation
                || $purchase->has_final_capture
                || $data['final']) {
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

            Capture::query()->create([
                'network_id' => $data['id'],
                'purchase_id' => $purchase->id,
                'sequence' => $data['sequence'],
                'amount_cents' => $data['amount_cents'],
                'currency' => $data['currency'],
                'final' => $data['final'],
                'occurred_at' => $data['occurred_at'],
            ]);

            if ($authorizationWasDeclined) {
                PurchaseIssue::query()->firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'code' => 'capture_for_declined_authorization',
                    ],
                    [
                        'related_network_id' => $data['id'],
                        'detected_at' => now(),
                    ],
                );
            }

            if ($captureAfterCancellation) {
                PurchaseIssue::query()->firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'code' => 'capture_after_cancellation',
                    ],
                    [
                        'related_network_id' => $data['id'],
                        'detected_at' => now(),
                    ],
                );
            }

            if ($overExpected) {
                PurchaseIssue::query()->firstOrCreate(
                    [
                        'purchase_id' => $purchase->id,
                        'code' => 'overcapture_exceeded',
                    ],
                    [
                        'related_network_id' => $data['id'],
                        'detected_at' => now(),
                    ],
                );
            }

            $hasFinalCapture = $purchase->has_final_capture || $data['final'];

            $purchase->update([
                'captured_amount_cents' => $capturedAfter,
                'reserved_amount_cents' => $reservedAfter,
                'has_final_capture' => $hasFinalCapture,
                'status' => $purchase->has_cancellation
                    ? 'canceled'
                    : ($hasFinalCapture ? 'settled' : 'partially_captured'),
            ]);

            if ($cardLimitDelta !== 0) {
                $monthBalance->increment(
                    'limit_remaining_cents',
                    $cardLimitDelta,
                );
            }

            $company->decrement(
                'balance_cents',
                $data['amount_cents'],
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
                'reference' => $data['id'],
                'type' => 'capture_settlement',
                'card_limit_delta_cents' => $cardLimitDelta,
                'company_balance_delta_cents' => -$data['amount_cents'],
                'company_reserved_delta_cents' => $companyReservedDelta,
                'occurred_at' => $data['occurred_at'],
            ]);

            return ['received' => true];
        });
    }
}
