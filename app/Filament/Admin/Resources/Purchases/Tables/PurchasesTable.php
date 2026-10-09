<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('authorization_network_id')
                    ->label('Autorização')
                    ->searchable(),

                TextColumn::make('card.user.name')
                    ->label('Funcionário')
                    ->placeholder('Pendente')
                    ->searchable(),

                TextColumn::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('Pendente')
                    ->searchable(),

                TextColumn::make('month')
                    ->label('Mês')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'pending_authorization' => 'Aguardando autorização',
                            'authorized' => 'Autorizada',
                            'partially_captured' => 'Parcialmente capturada',
                            'settled' => 'Liquidada',
                            'canceled' => 'Cancelada',
                            'declined' => 'Recusada',
                            default => $state,
                        },
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'authorized' => 'success',
                            'partially_captured' => 'warning',
                            'settled' => 'success',
                            'canceled' => 'gray',
                            'declined' => 'danger',
                            'pending_authorization' => 'warning',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('authorized_amount_cents')
                    ->label('Autorizado')
                    ->formatStateUsing(
                        fn ($state): string => $state === null
                            ? '-'
                            : 'R$ '.number_format(
                                ((int) $state) / 100,
                                2,
                                ',',
                                '.',
                            ),
                    )
                    ->sortable(),

                TextColumn::make('captured_amount_cents')
                    ->label('Capturado')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('reserved_amount_cents')
                    ->label('Reservado')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                IconColumn::make('has_final_capture')
                    ->label('Captura final')
                    ->boolean(),

                IconColumn::make('has_cancellation')
                    ->label('Cancelada')
                    ->boolean(),

                TextColumn::make('issues_count')
                    ->label('Alertas')
                    ->counts('issues'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending_authorization' => 'Aguardando autorização',
                        'authorized' => 'Autorizada',
                        'partially_captured' => 'Parcialmente capturada',
                        'settled' => 'Liquidada',
                        'canceled' => 'Cancelada',
                        'declined' => 'Recusada',
                    ]),
            ])
            ->emptyStateHeading('Nenhuma compra encontrada')
            ->emptyStateDescription(
                'As compras processadas pela rede aparecerão aqui.',
            )
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
