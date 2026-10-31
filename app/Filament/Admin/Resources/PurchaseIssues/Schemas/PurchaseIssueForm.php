<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class PurchaseIssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('purchase_id')
                    ->relationship('purchase', 'id')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('related_network_id'),
                DateTimePicker::make('detected_at')
                    ->required(),
            ]);
    }
}
