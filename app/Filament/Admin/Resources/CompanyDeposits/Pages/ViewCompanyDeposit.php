<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Pages;

use App\Filament\Admin\Resources\CompanyDeposits\CompanyDepositResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

final class ViewCompanyDeposit extends ViewRecord
{
    protected static string $resource = CompanyDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
