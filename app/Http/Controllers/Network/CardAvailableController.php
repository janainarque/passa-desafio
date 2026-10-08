<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardMonthBalance;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class CardAvailableController extends Controller
{
    public function show(string $cardToken): JsonResponse
    {
        $card = Card::query()
            ->where('card_token', $cardToken)
            ->firstOrFail();

        $month = CarbonImmutable::now('America/Sao_Paulo')
            ->format('Y-m');

        $monthBalance = CardMonthBalance::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->first();

        $limitRemaining = $monthBalance?->limit_remaining_cents
            ?? $card->monthly_limit_cents;

        $company = $card->company;

        $companyAvailable = max(
            $company->balance_cents - $company->reserved_cents,
            0,
        );

        $available = $card->status === 'blocked'
            ? 0
            : max(
                min($limitRemaining, $companyAvailable),
                0,
            );

        return response()->json([
            'available_cents' => $available,
            'limit_remaining_cents' => $limitRemaining,
        ]);
    }
}
