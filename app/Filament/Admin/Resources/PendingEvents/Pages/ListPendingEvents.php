<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingEvents\Pages;

use App\Filament\Admin\Resources\PendingEvents\PendingEventResource;
use Filament\Resources\Pages\ListRecords;

final class ListPendingEvents extends ListRecords
{
    protected static string $resource = PendingEventResource::class;
}
