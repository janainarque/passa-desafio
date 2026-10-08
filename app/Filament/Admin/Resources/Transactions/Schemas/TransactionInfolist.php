<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),

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
                    ->dateTime(),

                TextEntry::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
