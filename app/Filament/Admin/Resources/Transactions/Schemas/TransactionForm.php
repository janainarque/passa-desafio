<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('purchase_id')
                    ->relationship('purchase', 'id'),
                Select::make('card_id')
                    ->relationship('card', 'id'),
                TextInput::make('month'),
                TextInput::make('reference'),
                TextInput::make('type')
                    ->required(),
                TextInput::make('card_limit_delta_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('company_balance_delta_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('company_reserved_delta_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('occurred_at')
                    ->required(),
                TextInput::make('company_id')
                    ->numeric(),
            ]);
    }
}
