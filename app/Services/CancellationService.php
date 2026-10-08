<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cancellation;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class CancellationService
{
    public function process(array $data): array
    {
        $existingCancellation = Cancellation::query()
            ->where('network_id', $data['id'])
            ->first();

        if ($existingCancellation !== null) {
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
                    'has_cancellation' => true,
                ]);

                Cancellation::query()->create([
                    'network_id' => $data['id'],
                    'purchase_id' => $purchase->id,
                    'occurred_at' => $data['occurred_at'],
                ]);

                return [
                    'received' => true,
                    'pending' => true,
                ];
            }

            if ($purchase->has_cancellation) {
                return ['received' => true];
            }

            if ($purchase->card_id === null) {
                Cancellation::query()->create([
                    'network_id' => $data['id'],
                    'purchase_id' => $purchase->id,
                    'occurred_at' => $data['occurred_at'],
                ]);

                $purchase->update([
                    'has_cancellation' => true,
                ]);

                return [
                    'received' => true,
                    'pending' => true,
                ];
            }

            $reservedBefore = $purchase->reserved_amount_cents;

            Cancellation::query()->create([
                'network_id' => $data['id'],
                'purchase_id' => $purchase->id,
                'occurred_at' => $data['occurred_at'],
            ]);

            $purchase->update([
                'reserved_amount_cents' => 0,
                'has_cancellation' => true,
                'status' => 'canceled',
            ]);

            if ($reservedBefore > 0) {
                $company = Company::query()
                    ->whereHas(
                        'cards',
                        fn ($query) => $query->whereKey($purchase->card_id),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $monthBalance = CardMonthBalance::query()
                    ->where('card_id', $purchase->card_id)
                    ->where('month', $purchase->month)
                    ->lockForUpdate()
                    ->firstOrFail();

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
                    'reference' => $data['id'],
                    'type' => 'cancellation_release',
                    'card_limit_delta_cents' => $reservedBefore,
                    'company_balance_delta_cents' => 0,
                    'company_reserved_delta_cents' => -$reservedBefore,
                    'occurred_at' => $data['occurred_at'],
                ]);
            }

            return ['received' => true];
        });
    }
}
