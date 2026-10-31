<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits;

use App\Filament\Admin\Resources\CompanyDeposits\Pages\CreateCompanyDeposit;
use App\Filament\Admin\Resources\CompanyDeposits\Pages\ListCompanyDeposits;
use App\Filament\Admin\Resources\CompanyDeposits\Pages\ViewCompanyDeposit;
use App\Filament\Admin\Resources\CompanyDeposits\Schemas\CompanyDepositForm;
use App\Filament\Admin\Resources\CompanyDeposits\Schemas\CompanyDepositInfolist;
use App\Filament\Admin\Resources\CompanyDeposits\Tables\CompanyDepositsTable;
use App\Models\CompanyDeposit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class CompanyDepositResource extends Resource
{
    protected static ?string $model = CompanyDeposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Depósitos da empresa';

    protected static ?string $modelLabel = 'Depósito';

    protected static ?string $pluralModelLabel = 'Depósitos da empresa';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CompanyDepositForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompanyDepositInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyDepositsTable::configure($table);
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
            'index' => ListCompanyDeposits::route('/'),
            'create' => CreateCompanyDeposit::route('/create'),
            'view' => ViewCompanyDeposit::route('/{record}'),
        ];
    }
}
