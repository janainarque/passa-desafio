<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('authorization_network_id')
                    ->required(),
                Select::make('card_id')
                    ->relationship('card', 'id'),
                TextInput::make('month'),
                TextInput::make('status')
                    ->required()
                    ->default('pending_authorization'),
                TextInput::make('authorized_amount_cents')
                    ->numeric(),
                TextInput::make('captured_amount_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reserved_amount_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('has_final_capture')
                    ->required(),
                Toggle::make('has_cancellation')
                    ->required(),
            ]);
    }
}
