<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class CompanyDepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name')
                    ->required(),

                TextInput::make('amount_cents')
                    ->label('Valor em centavos')
                    ->required()
                    ->integer()
                    ->minValue(1),

                DateTimePicker::make('occurred_at')
                    ->label('Data do depósito')
                    ->required()
                    ->default(now()),
            ]);
    }
}
