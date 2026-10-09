<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AuthorizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da autorização')
                    ->description('Informações recebidas da rede para análise da compra.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('network_id')
                            ->label('ID da rede'),

                        TextEntry::make('card.user.name')
                            ->label('Funcionário')
                            ->placeholder('-'),

                        TextEntry::make('card.card_token')
                            ->label('Cartão')
                            ->placeholder('-'),

                        TextEntry::make('amount_cents')
                            ->label('Valor')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('currency')
                            ->label('Moeda'),

                        TextEntry::make('mcc')
                            ->label('MCC'),

                        TextEntry::make('merchant.name')
                            ->label('Estabelecimento')
                            ->placeholder('-'),

                        TextEntry::make('merchant.city')
                            ->label('Cidade')
                            ->placeholder('-'),

                        TextEntry::make('merchant.country')
                            ->label('País')
                            ->placeholder('-'),

                        TextEntry::make('purchase.authorization_network_id')
                            ->label('Compra relacionada')
                            ->placeholder('-'),
                    ]),

                Section::make('Decisão da autorização')
                    ->description('Resultado do processamento da autorização.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('decision')
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
                            ),

                        TextEntry::make('reason')
                            ->label('Motivo da recusa')
                            ->placeholder('Não se aplica')
                            ->formatStateUsing(
                                fn (?string $state): string => match ($state) {
                                    'amount_over_purchase_limit' => 'Acima do limite por compra',
                                    'card_blocked' => 'Cartão bloqueado',
                                    'mcc_blocked' => 'Categoria de uso bloqueada',
                                    'insufficient_card_limit' => 'Limite mensal insuficiente',
                                    'insufficient_company_balance' => 'Saldo da empresa insuficiente',
                                    'card_not_found' => 'Cartão não encontrado',
                                    null => 'Não se aplica',
                                    default => $state,
                                },
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
