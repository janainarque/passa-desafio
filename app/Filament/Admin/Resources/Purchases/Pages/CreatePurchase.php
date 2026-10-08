<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Pages;

use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;
}
