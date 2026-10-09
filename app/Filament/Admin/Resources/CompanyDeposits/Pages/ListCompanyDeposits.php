<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Pages;

use App\Filament\Admin\Resources\CompanyDeposits\CompanyDepositResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCompanyDeposits extends ListRecords
{
    protected static string $resource = CompanyDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
