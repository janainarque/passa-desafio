<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('authorization_network_id')
                    ->label('Autorização'),

                TextEntry::make('card.user.name')
                    ->label('Funcionário')
                    ->placeholder('Pendente'),

                TextEntry::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('Pendente'),

                TextEntry::make('month')
                    ->label('Mês')
                    ->placeholder('-'),

                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),

                TextEntry::make('authorized_amount_cents')
                    ->label('Valor autorizado')
                    ->numeric()
                    ->placeholder('-'),

                TextEntry::make('captured_amount_cents')
                    ->label('Valor capturado')
                    ->numeric(),

                TextEntry::make('reserved_amount_cents')
                    ->label('Valor reservado')
                    ->numeric(),

                IconEntry::make('has_final_capture')
                    ->label('Capture final')
                    ->boolean(),

                IconEntry::make('has_cancellation')
                    ->label('Cancelada')
                    ->boolean(),

                TextEntry::make('issues.code')
                    ->label('Problemas')
                    ->badge()
                    ->placeholder('Nenhum'),

                TextEntry::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
