<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CompanyDepositInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do depósito')
                    ->description(
                        'Informações financeiras e de registro da movimentação.',
                    )
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('company.name')
                            ->label('Empresa'),

                        TextEntry::make('amount_cents')
                            ->label('Valor do depósito')
                            ->formatStateUsing(
                                fn ($state): string => 'R$ '.number_format(
                                    ((int) $state) / 100,
                                    2,
                                    ',',
                                    '.',
                                ),
                            ),

                        TextEntry::make('occurred_at')
                            ->label('Data do depósito')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('createdBy.name')
                            ->label('Registrado por')
                            ->placeholder('-'),

                        TextEntry::make('created_at')
                            ->label('Registrado no sistema em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
