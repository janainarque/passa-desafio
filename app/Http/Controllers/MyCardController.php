<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MyCardController extends Controller
{
    public function show(Request $request): View
    {
        $card = Card::query()
            ->where('user_id', $request->user()->id)
            ->with('company')
            ->first();

        abort_if($card === null, 403);

        $month = CarbonImmutable::now('America/Sao_Paulo')
            ->format('Y-m');

        $monthBalance = CardMonthBalance::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->first();

        $limitRemaining = $monthBalance?->limit_remaining_cents
            ?? $card->monthly_limit_cents;

        $companyAvailable = max(
            $card->company->balance_cents
                - $card->company->reserved_cents,
            0,
        );

        $available = $card->status === 'blocked'
            ? 0
            : max(
                min($limitRemaining, $companyAvailable),
                0,
            );

        $transactions = Transaction::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $purchases = Purchase::query()
            ->where('card_id', $card->id)
            ->orderByDesc('created_at')
            ->get();

        return view('my-card', [
            'card' => $card,
            'month' => $month,
            'available' => $available,
            'limitRemaining' => $limitRemaining,
            'transactions' => $transactions,
            'purchases' => $purchases,
        ]);
    }
}
