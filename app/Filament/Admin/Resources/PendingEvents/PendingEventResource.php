<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingEvents;

use App\Filament\Admin\Resources\PendingEvents\Pages\ListPendingEvents;
use App\Filament\Admin\Resources\PendingEvents\Tables\PendingEventsTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PendingEventResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Aguardando autorização';

    protected static ?string $modelLabel = 'Evento aguardando autorização';

    protected static ?string $pluralModelLabel = 'Aguardando autorização';

    protected static ?string $recordTitleAttribute = 'authorization_network_id';

    public static function table(Table $table): Table
    {
        return PendingEventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'pending_authorization');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingEvents::route('/'),
        ];
    }
}
