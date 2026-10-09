<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues;

use App\Filament\Admin\Resources\PurchaseIssues\Pages\ListPurchaseIssues;
use App\Filament\Admin\Resources\PurchaseIssues\Pages\ViewPurchaseIssue;
use App\Filament\Admin\Resources\PurchaseIssues\Schemas\PurchaseIssueForm;
use App\Filament\Admin\Resources\PurchaseIssues\Schemas\PurchaseIssueInfolist;
use App\Filament\Admin\Resources\PurchaseIssues\Tables\PurchaseIssuesTable;
use App\Models\PurchaseIssue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class PurchaseIssueResource extends Resource
{
    protected static ?string $model = PurchaseIssue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Alertas financeiros';

    protected static ?string $modelLabel = 'Alerta financeiro';

    protected static ?string $pluralModelLabel = 'Alertas financeiros';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return PurchaseIssueForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseIssueInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseIssuesTable::configure($table);
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
            'index' => ListPurchaseIssues::route('/'),
            'view' => ViewPurchaseIssue::route('/{record}'),
        ];
    }
}
