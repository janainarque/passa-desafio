<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Pages;

use App\Filament\Admin\Resources\Cards\CardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewCard extends ViewRecord
{
    protected static string $resource = CardResource::class;

    public function getTitle(): string
    {
        return 'Detalhes do cartão';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para cartões')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CardResource::getUrl('index')),
        ];
    }
}
