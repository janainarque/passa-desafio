<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Pages;

use App\Filament\Admin\Resources\PurchaseIssues\PurchaseIssueResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchaseIssue extends ViewRecord
{
    protected static string $resource = PurchaseIssueResource::class;
}
