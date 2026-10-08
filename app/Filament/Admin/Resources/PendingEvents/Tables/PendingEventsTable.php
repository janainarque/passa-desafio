<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PendingEvents\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PendingEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('authorization_network_id')
                    ->label('Autorização aguardada')
                    ->searchable(),

                TextColumn::make('captures_count')
                    ->label('Captures pendentes')
                    ->counts('captures'),

                IconColumn::make('has_cancellation')
                    ->label('Cancellation recebida')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Última atualização')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
