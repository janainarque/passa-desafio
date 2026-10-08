<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class PurchaseIssueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')
                    ->label('Problema')
                    ->badge(),

                TextEntry::make('purchase.authorization_network_id')
                    ->label('Autorização'),

                TextEntry::make('purchase.card.user.name')
                    ->label('Funcionário')
                    ->placeholder('-'),

                TextEntry::make('purchase.card.card_token')
                    ->label('Cartão')
                    ->placeholder('-'),

                TextEntry::make('related_network_id')
                    ->label('Mensagem relacionada')
                    ->placeholder('-'),

                TextEntry::make('detected_at')
                    ->label('Detectado em')
                    ->dateTime(),

                TextEntry::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
