<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Pages;

use App\Filament\Admin\Resources\Authorizations\AuthorizationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewAuthorization extends ViewRecord
{
    protected static string $resource = AuthorizationResource::class;

    public function getTitle(): string
    {
        return 'Detalhes da autorização';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para autorizações')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AuthorizationResource::getUrl('index')),
        ];
    }
}
