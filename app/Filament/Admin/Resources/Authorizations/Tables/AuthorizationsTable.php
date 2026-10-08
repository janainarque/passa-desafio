<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AuthorizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('network_id')
                    ->label('ID da rede')
                    ->searchable(),

                TextColumn::make('card.user.name')
                    ->label('Funcionário')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('amount_cents')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('mcc')
                    ->label('MCC')
                    ->searchable(),

                TextColumn::make('decision')
                    ->label('Decisão')
                    ->badge()
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('decision')
                    ->label('Decisão')
                    ->options([
                        'approved' => 'Aprovada',
                        'declined' => 'Recusada',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
