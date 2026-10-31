<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Funcionário')
                    ->searchable(),

                TextColumn::make('card_token')
                    ->label('Token')
                    ->searchable(),

                TextColumn::make('status')
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

                TextColumn::make('monthly_limit_cents')
                    ->label('Limite mensal')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('purchase_limit_cents')
                    ->label('Limite por compra')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            ((int) $state) / 100,
                            2,
                            ',',
                            '.',
                        ),
                    )
                    ->sortable(),

                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
