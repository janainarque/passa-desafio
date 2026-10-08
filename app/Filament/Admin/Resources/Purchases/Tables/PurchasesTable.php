<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('authorization_network_id')
                    ->label('Autorização')
                    ->searchable(),

                TextColumn::make('card.user.name')
                    ->label('Funcionário')
                    ->placeholder('Pendente')
                    ->searchable(),

                TextColumn::make('card.card_token')
                    ->label('Cartão')
                    ->placeholder('Pendente')
                    ->searchable(),

                TextColumn::make('month')
                    ->label('Mês')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('authorized_amount_cents')
                    ->label('Autorizado')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('captured_amount_cents')
                    ->label('Capturado')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('reserved_amount_cents')
                    ->label('Reservado')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('has_final_capture')
                    ->label('Capture final')
                    ->boolean(),

                IconColumn::make('has_cancellation')
                    ->label('Cancelada')
                    ->boolean(),

                TextColumn::make('issues_count')
                    ->label('Problemas')
                    ->counts('issues'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
