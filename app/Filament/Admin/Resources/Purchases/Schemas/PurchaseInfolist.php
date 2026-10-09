<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo da compra')
                    ->description('Identificação e situação atual da compra.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('authorization_network_id')
                            ->label('Autorização'),

                        TextEntry::make('status')
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
                            ),

                        TextEntry::make('card.user.name')
                            ->label('Funcionário')
                            ->placeholder('Pendente'),

                        TextEntry::make('card.card_token')
                            ->label('Cartão')
                            ->placeholder('Pendente'),

                        TextEntry::make('month')
                            ->label('Mês')
                            ->placeholder('-'),
                    ]),

                Section::make('Valores e processamento')
                    ->description('Valores autorizados, capturados e reservados.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('authorized_amount_cents')
                            ->label('Valor autorizado')
                            ->formatStateUsing(
                                fn ($state): string => $state === null
                                    ? '-'
                                    : 'R$ '.number_format(
                                        ((int) $state) / 100,
                                        2,
                                        ',',
                                        '.',
                                    ),
                            ),

                        TextEntry::make('captured_amount_cents')
                            ->label('Valor capturado')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('reserved_amount_cents')
                            ->label('Valor reservado')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        IconEntry::make('has_final_capture')
                            ->label('Captura final')
                            ->boolean(),

                        IconEntry::make('has_cancellation')
                            ->label('Cancelada')
                            ->boolean(),

                        TextEntry::make('issues.code')
                            ->label('Alertas')
                            ->badge()
                            ->placeholder('Nenhum'),
                    ]),

                Section::make('Registro')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Atualizado em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
