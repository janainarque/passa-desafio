<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases;

use App\Filament\Admin\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Admin\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Admin\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Admin\Resources\Purchases\Schemas\PurchaseInfolist;
use App\Filament\Admin\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'authorization_network_id';

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}
