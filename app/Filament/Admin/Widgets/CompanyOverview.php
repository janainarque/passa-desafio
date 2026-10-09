<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CompanyOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $company = Company::query()->first();

        if ($company === null) {
            return [];
        }

        $balance = $company->balance_cents;
        $reserved = $company->reserved_cents;

        $available = max(
            $balance - $reserved,
            0,
        );

        return [
            Stat::make(
                'Saldo da empresa',
                $this->formatMoney($balance),
            )
                ->description('Depósitos ainda não consumidos por capturas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make(
                'Valor reservado',
                $this->formatMoney($reserved),
            )
                ->description('Comprometido com compras autorizadas em aberto')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('warning'),

            Stat::make(
                'Saldo disponível',
                $this->formatMoney($available),
            )
                ->description('Livre para novas autorizações')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }

    private function formatMoney(int $amountCents): string
    {
        return 'R$ '.number_format(
            $amountCents / 100,
            2,
            ',',
            '.',
        );
    }
}
