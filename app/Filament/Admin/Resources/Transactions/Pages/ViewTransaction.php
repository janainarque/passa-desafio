<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    public function getTitle(): string
    {
        return 'Detalhes da transação';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para transações')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(TransactionResource::getUrl('index')),
        ];
    }
}
