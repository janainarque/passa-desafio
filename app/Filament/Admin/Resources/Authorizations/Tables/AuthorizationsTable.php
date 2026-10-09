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
                    ->dateTime('d/m/Y H:i:s')
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
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'approved' => 'Aprovada',
                            'declined' => 'Recusada',
                            default => $state,
                        },
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'approved' => 'success',
                            'declined' => 'danger',
                            default => 'gray',
                        },
                    )
                    ->searchable(),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('-')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'amount_over_purchase_limit' => 'Acima do limite por compra',
                            'card_blocked' => 'Cartão bloqueado',
                            'mcc_blocked' => 'Categoria de uso bloqueada',
                            'insufficient_card_limit' => 'Limite mensal insuficiente',
                            'insufficient_company_balance' => 'Saldo da empresa insuficiente',
                            'card_not_found' => 'Cartão não encontrado',
                            null => '-',
                            default => $state,
                        },
                    )
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('decision')
                    ->label('Decisão')
                    ->options([
                        'approved' => 'Aprovada',
                        'declined' => 'Recusada',
                    ]),
            ])
            ->emptyStateHeading('Nenhuma autorização recebida')
            ->emptyStateDescription(
                'As autorizações enviadas pela rede aparecerão aqui.',
            )
            ->emptyStateIcon('heroicon-o-check-badge')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
