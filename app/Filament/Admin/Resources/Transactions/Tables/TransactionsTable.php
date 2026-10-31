<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Tables;

use App\Models\Transaction;
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
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'company_deposit' => 'Depósito da empresa',
                            'authorization_hold' => 'Reserva da autorização',
                            'capture_settlement' => 'Liquidação da captura',
                            'cancellation_release' => 'Liberação por cancelamento',
                            default => $state,
                        },
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'company_deposit' => 'success',
                            'authorization_hold' => 'warning',
                            'capture_settlement' => 'primary',
                            'cancellation_release' => 'gray',
                            default => 'gray',
                        },
                    ),

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
                    ->sortable(),
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
                        fn (): array => Transaction::query()
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
