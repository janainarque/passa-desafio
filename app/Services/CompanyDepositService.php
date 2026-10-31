<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyDeposit;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class CompanyDepositService
{
    public function create(
        int $companyId,
        int $amountCents,
        int $createdByUserId,
        string $occurredAt,
    ): CompanyDeposit {
        return DB::transaction(function () use (
            $companyId,
            $amountCents,
            $createdByUserId,
            $occurredAt,
        ): CompanyDeposit {
            $company = Company::query()
                ->whereKey($companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $deposit = CompanyDeposit::query()->create([
                'company_id' => $company->id,
                'amount_cents' => $amountCents,
                'created_by_user_id' => $createdByUserId,
                'occurred_at' => $occurredAt,
            ]);

            $company->increment(
                'balance_cents',
                $amountCents,
            );

            Transaction::query()->create([
                'company_id' => $company->id,
                'purchase_id' => null,
                'card_id' => null,
                'month' => null,
                'reference' => 'deposit:'.$deposit->id,
                'type' => 'company_deposit',
                'card_limit_delta_cents' => 0,
                'company_balance_delta_cents' => $amountCents,
                'company_reserved_delta_cents' => 0,
                'occurred_at' => $occurredAt,
            ]);

            return $deposit;
        });
    }
}
