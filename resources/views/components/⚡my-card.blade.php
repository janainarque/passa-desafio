<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function dashboard(): array
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $card = Card::query()
            ->where('user_id', $user->id)
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
            ->with([
                'authorization',
                'captures' => fn ($query) => $query
                    ->orderBy('occurred_at')
                    ->orderBy('sequence'),
                'cancellation',
            ])
            ->orderByDesc('created_at')
            ->get();

        return [
            'card' => $card,
            'month' => $month,
            'available' => $available,
            'limitRemaining' => $limitRemaining,
            'transactions' => $transactions,
            'purchases' => $purchases,
        ];
    }
};
?>

<div wire:poll.5s="$refresh">
    @php
        $data = $this->dashboard;
        $card = $data['card'];
        $month = $data['month'];
        $available = $data['available'];
        $limitRemaining = $data['limitRemaining'];
        $transactions = $data['transactions'];
        $purchases = $data['purchases'];
    @endphp

    <div class="mb-6 rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-semibold">Dados do cartão</h2>

        <p>
            <strong>Funcionário:</strong>
            {{ auth()->user()->name }}
        </p>

        <p>
            <strong>Cartão:</strong>
            {{ $card->card_token }}
        </p>

        <p>
            <strong>Status:</strong>
            {{ $card->status }}
        </p>

        <p>
            <strong>Disponível:</strong>
            R$ {{ number_format($available / 100, 2, ',', '.') }}
        </p>

        <p>
            <strong>Limite restante:</strong>
            R$ {{ number_format($limitRemaining / 100, 2, ',', '.') }}
        </p>

        <p>
            <strong>Mês:</strong>
            {{ $month }}
        </p>
    </div>

    <div class="mb-6 rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-semibold">Statement do mês</h2>

        @forelse ($transactions as $transaction)
            <div class="border-b py-3">
                <p>
                    <strong>Tipo:</strong>
                    {{ $transaction->type }}
                </p>

                <p>
                    <strong>Valor:</strong>
                    R$ {{ number_format(
                        $transaction->card_limit_delta_cents / 100,
                        2,
                        ',',
                        '.'
                    ) }}
                </p>

                <p>
                    <strong>Referência:</strong>
                    {{ $transaction->reference ?? '-' }}
                </p>

                <p>
                    <strong>Data:</strong>
                    {{ $transaction->occurred_at }}
                </p>
            </div>
        @empty
            <p>Nenhuma movimentação neste mês.</p>
        @endforelse
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-xl font-semibold">Histórico de compras</h2>

        @forelse ($purchases as $purchase)
            <div class="mb-6 border-b pb-5">
                <p>
                    <strong>Autorização:</strong>
                    {{ $purchase->authorization_network_id }}
                </p>

                <p>
                    <strong>Status:</strong>
                    {{ $purchase->status }}
                </p>

                @if ($purchase->authorization)
                    <p>
                        <strong>Decisão:</strong>
                        {{ $purchase->authorization->decision }}
                    </p>

                    <p>
                        <strong>Motivo:</strong>
                        {{ $purchase->authorization->reason ?? '-' }}
                    </p>

                    <p>
                        <strong>Data da autorização:</strong>
                        {{ $purchase->authorization->occurred_at }}
                    </p>
                @endif

                <p class="mt-3 font-semibold">Capturas</p>

                @forelse ($purchase->captures as $capture)
                    <div class="ml-4 py-1">
                        <p>
                            R$ {{ number_format(
                                $capture->amount_cents / 100,
                                2,
                                ',',
                                '.'
                            ) }} —
                            sequência {{ $capture->sequence }}
                            @if ($capture->final)
                                — final
                            @endif
                        </p>

                        <p>{{ $capture->occurred_at }}</p>
                    </div>
                @empty
                    <p class="ml-4">Nenhuma captura.</p>
                @endforelse

                @if ($purchase->cancellation)
                    <div class="mt-3">
                        <p class="font-semibold">Cancelamento</p>

                        <p>{{ $purchase->cancellation->occurred_at }}</p>
                    </div>
                @endif
            </div>
        @empty
            <p>Nenhuma compra encontrada.</p>
        @endforelse
    </div>
</div>
