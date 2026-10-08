<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class CompanyDepositInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company.name')
                    ->label('Empresa'),

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

                TextEntry::make('created_by_user_id')
                    ->label('Criado por'),

                TextEntry::make('occurred_at')
                    ->label('Data do depósito')
                    ->dateTime(),

                TextEntry::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
