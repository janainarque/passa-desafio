<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class CardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('Funcionário'),

                TextEntry::make('company.name')
                    ->label('Empresa'),

                TextEntry::make('card_token')
                    ->label('Token'),

                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),

                TextEntry::make('monthly_limit_cents')
                    ->label('Limite mensal')
                    ->numeric(),

                TextEntry::make('purchase_limit_cents')
                    ->label('Limite por compra')
                    ->numeric()
                    ->placeholder('Sem limite específico'),

                TextEntry::make('blocked_mccs')
                    ->label('MCCs bloqueados')
                    ->formatStateUsing(
                        fn ($state): string => empty($state)
                            ? 'Nenhum'
                            : implode(', ', $state),
                    ),

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
