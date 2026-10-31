<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Pages;

use App\Filament\Admin\Resources\PurchaseIssues\PurchaseIssueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchaseIssue extends ViewRecord
{
    protected static string $resource = PurchaseIssueResource::class;

    public function getTitle(): string
    {
        return 'Detalhes do alerta financeiro';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Voltar para alertas')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(PurchaseIssueResource::getUrl('index')),
        ];
    }
}
