<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo da transação')
                    ->description('Identificação e contexto da movimentação.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string => match ($state) {
                                    'company_deposit' => 'Depósito da empresa',
                                    'authorization' => 'Autorização',
                                    'capture' => 'Captura',
                                    'cancellation' => 'Cancelamento',
                                    default => $state,
                                },
                            ),

                        TextEntry::make('reference')
                            ->label('Referência')
                            ->placeholder('-'),

                        TextEntry::make('purchase.authorization_network_id')
                            ->label('Autorização')
                            ->placeholder('-'),

                        TextEntry::make('card.user.name')
                            ->label('Funcionário')
                            ->placeholder('-'),

                        TextEntry::make('card.card_token')
                            ->label('Cartão')
                            ->placeholder('-'),

                        TextEntry::make('month')
                            ->label('Mês')
                            ->placeholder('-'),
                    ]),

                Section::make('Impactos financeiros')
                    ->description('Efeito dessa movimentação nos saldos e limites.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('card_limit_delta_cents')
                            ->label('Impacto no limite')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('company_balance_delta_cents')
                            ->label('Impacto no saldo da empresa')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('company_reserved_delta_cents')
                            ->label('Impacto na reserva')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('occurred_at')
                            ->label('Ocorrido em')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('created_at')
                            ->label('Registrado no sistema em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
