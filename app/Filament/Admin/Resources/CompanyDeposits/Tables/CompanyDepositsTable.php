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
                    ->label('Data')
                    ->dateTime()
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

                TextColumn::make('created_by_user_id')
                    ->label('Criado por'),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
