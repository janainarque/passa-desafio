<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Pages;

use App\Filament\Admin\Resources\CompanyDeposits\CompanyDepositResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewCompanyDeposit extends ViewRecord
{
    protected static string $resource = CompanyDepositResource::class;

    public function getTitle(): string
    {
        return 'Detalhes do depósito';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para depósitos')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CompanyDepositResource::getUrl('index')),
        ];
    }
}
