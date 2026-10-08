<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Meu cartão</title>

    @vite (['resources/css/app.css', 'resources/js/app.js'])

    <meta http-equiv="refresh" content="5" />
</head>
<body class="bg-gray-100">
    <main class="mx-auto max-w-5xl p-6">
        <h1 class="mb-6 text-2xl font-bold">Meu cartão</h1>

        <form method="POST" action="{{ route('logout') }}" class="mb-6">
            @csrf

            <button type="submit" class="rounded border px-4 py-2">Sair</button>
        </form>

        <div class="mb-6 rounded-lg bg-white p-6 shadow">
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
                <strong>Mês atual:</strong>
                {{ $month }}
            </p>
        </div>

        <div class="mb-6 rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-xl font-semibold">Extrato do mês</h2>

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
                <div class="border-b py-3">
                    <p>
                        <strong>Autorização:</strong>
                        {{ $purchase->authorization_network_id }}
                    </p>

                    <p>
                        <strong>Status:</strong>
                        {{ $purchase->status }}
                    </p>

                    <p>
                        <strong>Autorizado:</strong>
                        R$ {{ number_format(
                            ($purchase->authorized_amount_cents ?? 0) / 100,
                            2,
                            ',',
                            '.'
                        ) }}
                    </p>

                    <p>
                        <strong>Capturado:</strong>
                        R$ {{ number_format(
                            $purchase->captured_amount_cents / 100,
                            2,
                            ',',
                            '.'
                        ) }}
                    </p>
                </div>
            @empty
                <p>Nenhuma compra encontrada.</p>
            @endforelse
        </div>
    </main>
</body>
</html>
