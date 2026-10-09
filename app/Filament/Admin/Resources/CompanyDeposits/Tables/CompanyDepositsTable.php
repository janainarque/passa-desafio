<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CompanyDeposits\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CompanyDepositsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Data do depósito')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable(),

                TextColumn::make('amount_cents')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Registrado por')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([])
            ->emptyStateHeading('Nenhum depósito registrado')
            ->emptyStateDescription(
                'Os depósitos realizados para a empresa aparecerão aqui.',
            )
            ->emptyStateIcon('heroicon-o-banknotes')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
