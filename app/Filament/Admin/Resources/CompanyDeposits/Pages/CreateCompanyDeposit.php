<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Pages;

use App\Filament\Admin\Resources\CompanyDeposits\CompanyDepositResource;
use App\Services\CompanyDepositService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCompanyDeposit extends CreateRecord
{
    protected static string $resource = CompanyDepositResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $userId = auth()->id();

        abort_if($userId === null, 403);

        $amountCents = (int) round(
            ((float) $data['amount_reais']) * 100,
        );

        return resolve(CompanyDepositService::class)->create(
            companyId: (int) $data['company_id'],
            amountCents: $amountCents,
            createdByUserId: $userId,
            occurredAt: (string) $data['occurred_at'],
        );
    }
}
