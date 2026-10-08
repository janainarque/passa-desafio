<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class PurchaseIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('detected_at')
                    ->label('Detectado em')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Problema')
                    ->badge()
                    ->searchable(),

                TextColumn::make('purchase.authorization_network_id')
                    ->label('Autorização')
                    ->searchable(),

                TextColumn::make('purchase.card.user.name')
                    ->label('Funcionário')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('purchase.card.card_token')
                    ->label('Cartão')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('related_network_id')
                    ->label('Mensagem relacionada')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Registrado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('code')
                    ->label('Tipo de problema')
                    ->options([
                        'overcapture_exceeded' => 'Captura acima do esperado',
                        'capture_after_cancellation' => 'Captura após cancelamento',
                        'capture_for_declined_authorization' => 'Captura de autorização recusada',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
