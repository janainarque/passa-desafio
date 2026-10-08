<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Pages;

use App\Filament\Admin\Resources\PurchaseIssues\PurchaseIssueResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePurchaseIssue extends CreateRecord
{
    protected static string $resource = PurchaseIssueResource::class;
}
