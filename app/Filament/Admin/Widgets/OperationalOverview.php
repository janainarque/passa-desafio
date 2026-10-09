<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Card;
use App\Models\Purchase;
use App\Models\PurchaseIssue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class OperationalOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $activeCards = Card::query()
            ->where('status', 'active')
            ->count();

        $pendingEvents = Purchase::query()
            ->where('status', 'pending_authorization')
            ->count();

        $financialAlerts = PurchaseIssue::query()
            ->count();

        return [
            Stat::make(
                'Cartões ativos',
                (string) $activeCards,
            )
                ->description('Cartões disponíveis para utilização')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make(
                'Eventos aguardando autorização',
                (string) $pendingEvents,
            )
                ->description('Eventos recebidos antes da autorização')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingEvents > 0 ? 'warning' : 'gray'),

            Stat::make(
                'Alertas financeiros',
                (string) $financialAlerts,
            )
                ->description('Situações financeiras que exigem atenção')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($financialAlerts > 0 ? 'danger' : 'gray'),
        ];
    }
}
