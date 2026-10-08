<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Pages;

use App\Filament\Admin\Resources\PurchaseIssues\PurchaseIssueResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditPurchaseIssue extends EditRecord
{
    protected static string $resource = PurchaseIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
