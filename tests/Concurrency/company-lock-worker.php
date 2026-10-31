<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

require __DIR__.'/guard.php';

$suffix = $argv[1] ?? '';

if (! preg_match('/^[a-f0-9]{12}$/', $suffix)) {
    throw new RuntimeException('Identificador de auditoria invalido.');
}

$company = Company::query()
    ->where('name', "Auditoria Autorizacoes {$suffix}")
    ->firstOrFail();

$cards = $company->cards()->with('user')->get();

if (
    $cards->count() !== 2
    || (int) $company->balance_cents !== 100000
    || (int) $company->reserved_cents !== 0
) {
    throw new RuntimeException('Empresa fora do cenario esperado.');
}

foreach ([1, 2] as $number) {
    $card = $cards->firstWhere(
        'card_token',
        "tok_concurrent_{$suffix}_{$number}",
    );

    if (
        $card === null
        || $card->user === null
        || $card->user->email !== "audit_concurrent_{$suffix}_{$number}@example.test"
    ) {
        throw new RuntimeException('Cartoes fora do cenario esperado.');
    }
}

DB::beginTransaction();

try {
    Company::query()
        ->whereKey($company->id)
        ->lockForUpdate()
        ->firstOrFail();

    $pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
    echo 'LOCK_PID='.$pid.PHP_EOL;
    echo 'LOCK_ACQUIRED'.PHP_EOL;
    flush();

    // Permite que dois processos disputem o bloqueio.
    Sleep::sleep(8);
} finally {
    DB::rollBack();

    echo 'LOCK_RELEASED'.PHP_EOL;
}
