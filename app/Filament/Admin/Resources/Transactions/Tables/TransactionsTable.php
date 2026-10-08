<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->searchable(),

                TextColumn::make('reference')
                    ->label('Referência')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('card.user.name')
                    ->label('Funcionário')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('month')
                    ->label('Mês')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('card_limit_delta_cents')
                    ->label('Impacto no limite')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('company_balance_delta_cents')
                    ->label('Impacto no saldo')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('company_reserved_delta_cents')
                    ->label('Impacto na reserva')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'company_deposit' => 'Depósito',
                        'authorization_hold' => 'Reserva de autorização',
                        'capture_settlement' => 'Captura',
                        'cancellation_release' => 'Cancelamento',
                    ]),

                SelectFilter::make('month')
                    ->label('Mês')
                    ->options(
                        fn (): array => \App\Models\Transaction::query()
                            ->whereNotNull('month')
                            ->distinct()
                            ->orderByDesc('month')
                            ->pluck('month', 'month')
                            ->all(),
                    ),

                SelectFilter::make('card_id')
                    ->label('Cartão')
                    ->relationship(
                        'card',
                        'card_token',
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
