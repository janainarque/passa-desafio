<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Pages;

use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ListRecords;

final class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;
}
