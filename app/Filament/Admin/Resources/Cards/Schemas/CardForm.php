<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class CardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('monthly_limit_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('purchase_limit_cents')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('blocked_mccs')
                    ->required()
                    ->default('[]'),
            ]);
    }
}
