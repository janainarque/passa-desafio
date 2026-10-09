<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Pages;

use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    public function getTitle(): string
    {
        return 'Detalhes da compra';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para compras')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PurchaseResource::getUrl('index')),
        ];
    }
}
