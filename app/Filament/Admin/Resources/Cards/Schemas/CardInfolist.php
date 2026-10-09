<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo do cartão')
                    ->description('Informações principais do cartão corporativo.')
                    ->columns(2)
                    ->components([
                        TextEntry::make('user.name')
                            ->label('Funcionário')
                            ->placeholder('-'),

                        TextEntry::make('company.name')
                            ->label('Empresa')
                            ->placeholder('-'),

                        TextEntry::make('card_token')
                            ->label('Token')
                            ->placeholder('-'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string => match ($state) {
                                    'active' => 'Ativo',
                                    'blocked' => 'Bloqueado',
                                    default => $state,
                                },
                            )
                            ->color(
                                fn (string $state): string => match ($state) {
                                    'active' => 'success',
                                    'blocked' => 'danger',
                                    default => 'gray',
                                },
                            ),
                    ]),

                Section::make('Limites e restrições')
                    ->description('Regras de uso configuradas para este cartão.')
                    ->columns(2)
                    ->components([
                        TextEntry::make('monthly_limit_cents')
                            ->label('Limite mensal')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('purchase_limit_cents')
                            ->label('Limite por compra')
                            ->formatStateUsing(
                                fn ($state): string => $state === null
                                    ? 'Sem limite específico'
                                    : 'R$ '.number_format(
                                        ((int) $state) / 100,
                                        2,
                                        ',',
                                        '.',
                                    ),
                            ),

                        TextEntry::make('blocked_mccs')
                            ->label('MCCs bloqueados')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state): string {
                                if (blank($state)) {
                                    return 'Nenhum';
                                }

                                if (is_array($state)) {
                                    return implode(', ', $state);
                                }

                                $decoded = json_decode((string) $state, true);

                                if (is_array($decoded)) {
                                    return implode(', ', $decoded);
                                }

                                return (string) $state;
                            }),
                    ]),

                Section::make('Auditoria')
                    ->description('Informações de cadastro do registro.')
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
