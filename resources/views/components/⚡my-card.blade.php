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

        $limitCents = $monthBalance?->limit_cents
            ?? $card->monthly_limit_cents;

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
            ->with('purchase.authorization')
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
            'limitCents' => $limitCents,
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
        $limitCents = $data['limitCents'];
        $available = $data['available'];
        $limitRemaining = $data['limitRemaining'];
        $transactions = $data['transactions'];
        $purchases = $data['purchases'];

        $user = auth()->user();

        $initials = collect(explode(' ', trim($user->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $runningBalance = $limitCents;
    @endphp

    <div class="space-y-8">
        {{-- Cabeçalho interno --}}
        <section
            class="flex flex-col gap-4 border-b border-slate-800/80 pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-tr from-emerald-500 to-cyan-400 text-xl font-black tracking-tighter text-slate-950 shadow-lg shadow-emerald-500/10"
                >
                    P
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold tracking-tight text-white">Passa</h1>

                        <span
                            class="rounded-full border border-slate-700/60 bg-slate-800 px-2 py-0.5 text-[10px] font-semibold tracking-wider text-slate-400"
                        >
                            CORPORATE
                        </span>
                    </div>

                    <p class="text-xs text-slate-400">Cartão corporativo · {{ $card->company->name }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div
                    class="flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs text-slate-300"
                >
                    <span class="h-2 w-2 rounded-full bg-cyan-400"></span>

                    <span wire:loading.remove.delay> Atualização automática · 5s </span>

                    <span wire:loading.delay> Atualizando... </span>
                </div>

                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-xs font-bold text-emerald-400"
                    title="{{ $user->name }}"
                >
                    {{ $initials }}
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2 text-xs font-medium text-slate-400 transition hover:border-slate-700 hover:bg-slate-800 hover:text-white"
                    >
                        Sair
                    </button>
                </form>
            </div>
        </section>

        {{-- Cartão + indicadores --}}
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Cartão virtual --}}
            <div
                class="group relative flex min-h-[260px] flex-col justify-between overflow-hidden rounded-3xl border border-slate-700/60 bg-gradient-to-br from-slate-900 via-[#101726] to-[#0b101b] p-7 shadow-2xl transition hover:border-slate-600 lg:col-span-5"
            >
                <div
                    class="pointer-events-none absolute -right-12 -bottom-12 h-52 w-52 rounded-full bg-emerald-500/10 blur-3xl"
                ></div>

                <div class="relative flex items-center justify-between">
                    <div>
                        <p class="text-xl font-black tracking-tight text-white">Passa</p>

                        <p class="mt-0.5 text-[10px] font-semibold tracking-widest text-slate-500 uppercase">Cartão corporativo</p>
                    </div>

                    @if ($card->status === 'active')
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-[11px] font-semibold text-emerald-400"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Ativo
                        </span>
                    @else
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-500/20 bg-red-500/10 px-3 py-1 text-[11px] font-semibold text-red-400"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-400"></span>
                            Bloqueado
                        </span>
                    @endif
                </div>

                <div class="relative my-6">
                    <span class="mb-1 block text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                        Token do cartão
                    </span>

                    <span
                        class="inline-block rounded-lg border border-slate-800/80 bg-slate-950/40 px-3 py-1.5 font-mono text-base tracking-widest text-slate-200"
                    >
                        {{ $card->card_token }}
                    </span>
                </div>

                <div class="relative flex items-end justify-between border-t border-slate-800/60 pt-4">
                    <div>
                        <span class="block text-[9px] font-bold tracking-wider text-slate-500 uppercase">
                            Portador
                        </span>

                        <span class="text-sm font-bold tracking-wide text-white uppercase"> {{ $user->name }} </span>
                    </div>

                    <div class="text-right">
                        <span class="block text-[9px] font-bold tracking-wider text-slate-500 uppercase">
                            Mês de referência
                        </span>

                        <span class="font-mono text-xs font-medium text-slate-400"> {{ $month }} </span>
                    </div>
                </div>
            </div>

            {{-- Métricas --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-7">
                <div
                    class="flex flex-col justify-between rounded-3xl border border-slate-800/80 bg-slate-900/60 p-6 backdrop-blur-md"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Disponível para compras
                            </span>

                            @if ($card->status === 'active')
                                <span
                                    class="rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-400"
                                >
                                    Livre
                                </span>
                            @else
                                <span class="rounded-md bg-red-500/10 px-2 py-0.5 text-xs font-semibold text-red-400">
                                    Bloqueado
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 font-mono text-3xl font-extrabold tracking-tight text-emerald-400 sm:text-4xl">
                            R$ {{ number_format($available / 100, 2, ',', '.') }}
                        </div>
                    </div>

                    <p class="mt-4 text-[11px] leading-relaxed text-slate-500">Menor valor entre o limite mensal restante e o saldo disponível da empresa.</p>
                </div>

                <div
                    class="flex flex-col justify-between rounded-3xl border border-slate-800/80 bg-slate-900/60 p-6 backdrop-blur-md"
                >
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-bold tracking-wider text-slate-400 uppercase">
                                Limite restante do mês
                            </span>

                            <span class="font-mono text-xs text-slate-500"> {{ $month }} </span>
                        </div>

                        <div class="mt-3 font-mono text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                            R$ {{ number_format($limitRemaining / 100, 2, ',', '.') }}
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-slate-800/80 pt-3 text-xs text-slate-400"
                    >
                        <span>Limite mensal:</span>

                        <span class="font-mono font-bold text-slate-200">
                            R$ {{ number_format($limitCents / 100, 2, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 rounded-2xl border border-slate-800/60 bg-slate-900/40 px-5 py-4 text-xs text-slate-400 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500"> Teto por compra: </span>

                        <span class="font-mono font-semibold text-slate-200">
                            @if ($card->purchase_limit_cents !== null)
                                R$ {{ number_format($card->purchase_limit_cents / 100, 2, ',', '.') }}
                            @else
                                Sem teto individual
                            @endif
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-slate-500"> Restrições de uso: </span>

                            @if (! empty($card->blocked_mccs))
                                <span
                                    class="rounded border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-400"
                                >
                                    Categorias restritas
                                </span>
                            @else
                                <span class="text-slate-400"> Nenhuma </span>
                            @endif
                        </div>

                        @forelse ($card->blocked_mccs ?? [] as $mcc)
                            <span
                                class="rounded border border-red-500/20 bg-red-500/10 px-2 py-0.5 font-mono text-[11px] text-red-400"
                            >
                                {{ $mcc }}
                            </span>
                        @empty
                            <span class="text-slate-400"> Nenhum </span>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- Extrato --}}
        <section
            class="overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-900/60 shadow-2xl backdrop-blur-md"
        >
            <div
                class="flex flex-col gap-2 border-b border-slate-800/80 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-base font-bold tracking-tight text-white">Extrato do mês corrente</h2>

                    <p class="mt-0.5 text-xs text-slate-400">Movimentações que alteraram o limite disponível do cartão</p>
                </div>

                <div class="flex items-center gap-2 font-mono text-xs text-slate-500">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                    {{ $month }}
                </div>
            </div>

            <div class="divide-y divide-slate-800/60">
                @forelse ($transactions as $transaction)
                    @php
                        $runningBalance += $transaction->card_limit_delta_cents;

                        $transactionLabel = match ($transaction->type) {
                            'authorization_hold' => 'Reserva de autorização',
                            'capture_settlement' => 'Liquidação de compra',
                            'cancellation_release' => 'Liberação de reserva',
                            'company_deposit' => 'Depósito da empresa',
                            default => $transaction->type,
                        };

                        $delta = $transaction->card_limit_delta_cents;

                        $merchant = $transaction->purchase?->authorization?->merchant;
                        $merchantName = is_array($merchant)
                            ? ($merchant['name'] ?? null)
                            : null;

                        $mcc = $transaction->purchase?->authorization?->mcc;
                    @endphp

                    <div
                        class="flex flex-col gap-4 px-6 py-4 transition hover:bg-slate-800/30 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                @class ([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border text-sm font-bold',
                                'border-red-500/20 bg-red-500/10 text-red-400' => $delta < 0,
                                'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' => $delta > 0,
                                'border-slate-700 bg-slate-800 text-slate-400' => $delta === 0,
                            ])
                            >
                                @if ($delta < 0)
                                    ↓
                                @elseif ($delta > 0)
                                    ↑
                                @else
                                    •
                                @endif
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-100"> {{ $transactionLabel }} </span>

                                    @if ($mcc)
                                        <span
                                            class="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 font-mono text-[10px] text-slate-400"
                                        >
                                            MCC {{ $mcc }}
                                        </span>
                                    @endif
                                </div>

                                @if ($merchantName)
                                    <p class="mt-1 text-xs text-slate-400">{{ $merchantName }}</p>
                                @endif

                                <div
                                    class="mt-1 flex flex-wrap items-center gap-2 font-mono text-[11px] text-slate-500"
                                >
                                    <span>
                                        {{ CarbonImmutable::parse($transaction->occurred_at)
                                            ->setTimezone('America/Sao_Paulo')
                                            ->format('d/m/Y H:i:s') }}
                                    </span>

                                    <span>·</span>

                                    <span> Ref: {{ $transaction->reference ?? '-' }} </span>
                                </div>
                            </div>
                        </div>

                        <div class="text-left font-mono sm:text-right">
                            <div
                                @class ([
                                'text-sm font-bold',
                                'text-red-400' => $delta < 0,
                                'text-emerald-400' => $delta > 0,
                                'text-slate-400' => $delta === 0,
                            ])
                            >
                                @if ($delta > 0)
                                    +
                                @elseif ($delta < 0)
                                    -
                                @endif
                                R$ {{ number_format(abs($delta) / 100, 2, ',', '.') }}
                            </div>

                            <div class="mt-0.5 text-[11px] text-slate-500">
                                Limite após: R$ {{ number_format($runningBalance / 100, 2, ',', '.') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-medium text-slate-300">Nenhuma movimentação neste mês</p>

                        <p class="mt-1 text-xs text-slate-500">As movimentações do cartão aparecerão aqui automaticamente.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Histórico de compras --}}
        <section class="overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-900/60 shadow-2xl">
            <div class="border-b border-slate-800/80 px-6 py-5">
                <h2 class="text-base font-bold tracking-tight text-white">Histórico de compras</h2>

                <p class="mt-0.5 text-xs text-slate-400">Autorizações, capturas e cancelamentos relacionados ao seu cartão</p>
            </div>

            <div class="divide-y divide-slate-800/60">
                @forelse ($purchases as $purchase)
                    @php
                        $statusLabel = match ($purchase->status) {
                            'pending_authorization' => 'Pendente',
                            'authorized' => 'Autorizada',
                            'partially_captured' => 'Captura parcial',
                            'settled' => 'Finalizada',
                            'canceled' => 'Cancelada',
                            'declined' => 'Recusada',
                            default => $purchase->status,
                        };
                    @endphp

                    <details class="group px-6 py-5">
                        <summary
                            class="flex cursor-pointer list-none flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-semibold text-slate-100">
                                        {{ $purchase->authorization_network_id }}
                                    </span>

                                    <span
                                        @class ([
                                        'rounded-full border px-2.5 py-0.5 text-[10px] font-semibold',
                                        'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' => in_array($purchase->status, ['authorized', 'settled'], true),
                                        'border-amber-500/20 bg-amber-500/10 text-amber-400' => in_array($purchase->status, ['pending_authorization', 'partially_captured'], true),
                                        'border-red-500/20 bg-red-500/10 text-red-400' => in_array($purchase->status, ['declined', 'canceled'], true),
                                    ])
                                    >
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                @if ($purchase->authorization)
                                    @php
                                        $purchaseMerchant = $purchase->authorization->merchant;
                                        $purchaseMerchantName = is_array($purchaseMerchant)
                                            ? ($purchaseMerchant['name'] ?? null)
                                            : null;
                                    @endphp

                                    @if ($purchaseMerchantName)
                                        <p class="mt-1 text-xs text-slate-400">{{ $purchaseMerchantName }}</p>
                                    @endif
                                @endif
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="text-left font-mono sm:text-right">
                                    <div class="text-sm font-bold text-white">
                                        R$ {{ number_format(($purchase->authorized_amount_cents ?? 0) / 100, 2, ',', '.') }}
                                    </div>

                                    <div class="text-[11px] text-slate-500">autorizado</div>
                                </div>

                                <span class="text-slate-500 transition group-open:rotate-180"> ↓ </span>
                            </div>
                        </summary>

                        <div class="mt-5 space-y-4 border-t border-slate-800/70 pt-5">
                            @if ($purchase->authorization)
                                <div class="rounded-2xl border border-slate-800 bg-slate-950/30 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-bold tracking-wider text-slate-500 uppercase">Autorização</p>

                                            <p class="mt-1 text-sm font-semibold text-slate-200">
                                                {{ $purchase->authorization->decision === 'approved'
                                                    ? 'Aprovada'
                                                    : 'Recusada' }}
                                            </p>
                                        </div>

                                        <span class="font-mono text-sm text-slate-300">
                                            R$ {{ number_format($purchase->authorization->amount_cents / 100, 2, ',', '.') }}
                                        </span>
                                    </div>

                                    @if ($purchase->authorization->reason)
                                        <p class="mt-2 text-xs text-red-400">
                                            Motivo: {{ $purchase->authorization->reason }}
                                        </p>
                                    @endif

                                    <p class="mt-2 font-mono text-[11px] text-slate-500">
                                        {{ CarbonImmutable::parse($purchase->authorization->occurred_at)
                                            ->setTimezone('America/Sao_Paulo')
                                            ->format('d/m/Y H:i:s') }}
                                    </p>
                                </div>
                            @endif

                            <div>
                                <p class="mb-2 text-xs font-bold tracking-wider text-slate-500 uppercase">Capturas</p>

                                <div class="space-y-2">
                                    @forelse ($purchase->captures as $capture)
                                        <div
                                            class="flex flex-col gap-2 rounded-xl border border-slate-800/80 bg-slate-950/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p class="text-sm text-slate-300">
                                                    Captura #{{ $capture->sequence }}

                                                    @if ($capture->final)
                                                        <span
                                                            class="ml-1 rounded bg-cyan-500/10 px-2 py-0.5 text-[10px] font-semibold text-cyan-400"
                                                        >
                                                            FINAL
                                                        </span>
                                                    @endif
                                                </p>

                                                <p class="mt-1 font-mono text-[11px] text-slate-500">
                                                    {{ CarbonImmutable::parse($capture->occurred_at)
                                                        ->setTimezone('America/Sao_Paulo')
                                                        ->format('d/m/Y H:i:s') }}
                                                </p>
                                            </div>

                                            <span class="font-mono text-sm font-semibold text-slate-200">
                                                R$ {{ number_format($capture->amount_cents / 100, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500">Nenhuma captura registrada.</p>
                                    @endforelse
                                </div>
                            </div>

                            @if ($purchase->cancellation)
                                <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-4">
                                    <p class="text-xs font-bold tracking-wider text-red-400 uppercase">Cancelamento</p>

                                    <p class="mt-2 font-mono text-[11px] text-slate-400">
                                        {{ CarbonImmutable::parse($purchase->cancellation->occurred_at)
                                            ->setTimezone('America/Sao_Paulo')
                                            ->format('d/m/Y H:i:s') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </details>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-medium text-slate-300">Nenhuma compra encontrada</p>

                        <p class="mt-1 text-xs text-slate-500">Quando o cartão for utilizado, o histórico aparecerá aqui.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
