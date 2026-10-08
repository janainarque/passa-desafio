<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Passa - Login</title>

    @vite (['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100">
    <main class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <h1 class="mb-6 text-2xl font-bold">Passa</h1>

            <p class="mb-6 text-gray-600">Acesse sua área do cartão.</p>

            @if ($errors->any())
                <div class="mb-4 rounded bg-red-100 p-3 text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="mb-4">
                    <label for="email" class="mb-1 block font-medium"> E-mail </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="w-full rounded border p-2"
                    />
                </div>

                <div class="mb-6">
                    <label for="password" class="mb-1 block font-medium"> Senha </label>

                    <input id="password" name="password" type="password" required class="w-full rounded border p-2" />
                </div>

                <button type="submit" class="w-full rounded bg-black px-4 py-2 text-white">Entrar</button>
            </form>
        </div>
    </main>
</body>
</html>
