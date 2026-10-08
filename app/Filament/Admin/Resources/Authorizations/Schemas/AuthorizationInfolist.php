<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class AuthorizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
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

                TextEntry::make('decision')
                    ->label('Decisão')
                    ->badge(),

                TextEntry::make('reason')
                    ->label('Motivo da recusa')
                    ->placeholder('-'),

                TextEntry::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime(),

                TextEntry::make('purchase.authorization_network_id')
                    ->label('Compra')
                    ->placeholder('-'),

                TextEntry::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
