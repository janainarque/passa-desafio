<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseIssues\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PurchaseIssueInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo do alerta')
                    ->description('Informações principais da ocorrência financeira.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('code')
                            ->label('Problema')
                            ->badge()
                            ->formatStateUsing(
                                fn (string $state): string => match ($state) {
                                    'overcapture_exceeded' => 'Captura acima do esperado',
                                    'capture_after_cancellation' => 'Captura após cancelamento',
                                    'capture_for_declined_authorization' => 'Captura para autorização recusada',
                                    default => $state,
                                },
                            )
                            ->color('warning'),

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
                    ]),

                Section::make('Registro da ocorrência')
                    ->description('Datas relacionadas à identificação e ao registro do alerta.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('detected_at')
                            ->label('Detectado em')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('created_at')
                            ->label('Registrado no sistema em')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
