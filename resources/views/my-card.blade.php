<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Meu cartão</title>

    @vite (['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-gray-100">
    <main class="mx-auto max-w-5xl p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Meu cartão</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="rounded border px-4 py-2">Sair</button>
            </form>
        </div>

        <livewire:my-card />
    </main>

    @livewireScripts
</body>
</html>
