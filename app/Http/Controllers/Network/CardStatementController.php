<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardStatementController extends Controller
{
    public function show(Request $request, string $cardToken): JsonResponse
    {
        $validated = $request->validate([
            'month' => [
                'nullable',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],
        ]);

        $card = Card::query()
            ->where('card_token', $cardToken)
            ->firstOrFail();

        $month = $validated['month']
            ?? CarbonImmutable::now('America/Sao_Paulo')->format('Y-m');

        $monthBalance = CardMonthBalance::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->first();

        $limitCents = $monthBalance?->limit_cents
            ?? $card->monthly_limit_cents;

        $transactions = Transaction::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $runningBalance = $limitCents;

        $statementTransactions = $transactions
            ->map(function (Transaction $transaction) use (&$runningBalance): array {
                $runningBalance += $transaction->card_limit_delta_cents;

                return [
                    'occurred_at' => CarbonImmutable::parse(
                        $transaction->getRawOriginal('occurred_at'),
                        'UTC',
                    )->format('Y-m-d\TH:i:s\Z'),
                    'type' => $transaction->type,
                    'amount_cents' => $transaction->card_limit_delta_cents,
                    'reference' => $transaction->reference,
                    'limit_remaining_after_cents' => $runningBalance,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'month' => $month,
            'limit_cents' => $limitCents,
            'limit_remaining_cents' => $runningBalance,
            'transactions' => $statementTransactions,
        ]);
    }
}
