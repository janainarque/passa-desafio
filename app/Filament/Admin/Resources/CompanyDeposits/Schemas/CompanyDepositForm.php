<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CompanyDepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registrar depósito')
                    ->description(
                        'Informe a empresa, o valor e a data da movimentação.',
                    )
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->required(),

                        TextInput::make('amount_reais')
                            ->label('Valor do depósito')
                            ->prefix('R$')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->placeholder('0,00')
                            ->required(),

                        DateTimePicker::make('occurred_at')
                            ->label('Data do depósito')
                            ->required()
                            ->default(now())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
