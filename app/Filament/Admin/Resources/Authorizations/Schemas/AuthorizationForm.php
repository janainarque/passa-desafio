<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class AuthorizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('network_id')
                    ->required(),
                Select::make('purchase_id')
                    ->relationship('purchase', 'id')
                    ->required(),
                Select::make('card_id')
                    ->relationship('card', 'id'),
                TextInput::make('amount_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required(),
                TextInput::make('mcc')
                    ->required(),
                TextInput::make('merchant')
                    ->required(),
                TextInput::make('decision')
                    ->required(),
                TextInput::make('reason'),
                DateTimePicker::make('occurred_at')
                    ->required(),
            ]);
    }
}
