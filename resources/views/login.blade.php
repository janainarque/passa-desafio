<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Entrar · Passa</title>

    @vite (['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#090d16] text-slate-100 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            {{-- Marca --}}
            <div class="mb-8 flex flex-col items-center text-center">
                <div
                    class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-emerald-500 to-cyan-400 text-2xl font-black tracking-tighter text-slate-950 shadow-lg shadow-emerald-500/10"
                >
                    P
                </div>

                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-white">Passa</h1>

                    <span
                        class="rounded-full border border-slate-700/60 bg-slate-800 px-2 py-0.5 text-[10px] font-semibold tracking-wider text-slate-400"
                    >
                        CORPORATE
                    </span>
                </div>

                <p class="mt-2 text-sm text-slate-400">Acesse sua área do cartão corporativo</p>
            </div>

            {{-- Card de login --}}
            <section class="rounded-3xl border border-slate-800/80 bg-slate-900/70 p-7 shadow-2xl backdrop-blur-md">
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-white">Entrar</h2>

                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Use suas credenciais para consultar seu cartão, limite e movimentações.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            E-mail
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="nome@empresa.com"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white transition outline-none placeholder:text-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                        />
                    </div>

                    <div>
                        <label
                            for="password"
                            class="mb-2 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Senha
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="Digite sua senha"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-sm text-white transition outline-none placeholder:text-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10"
                        />
                    </div>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-400 px-4 py-3 text-sm font-bold text-slate-950 transition hover:brightness-110 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                    >
                        Entrar
                    </button>
                </form>
            </section>

            {{-- Rodapé --}}
            <div class="mt-6 text-center">
                <p class="text-[11px] text-slate-600">Acesso exclusivo para portadores do cartão corporativo Passa.</p>
            </div>
        </div>
    </main>
</body>
</html>
