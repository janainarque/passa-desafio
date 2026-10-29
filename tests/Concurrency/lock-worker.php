<?php

declare(strict_types=1);

use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

require __DIR__.'/guard.php';

$authorizationId = $argv[1] ?? '';

if (! preg_match(
    '/^audit_auth_([a-f0-9]{12})$/',
    $authorizationId,
    $matches,
)) {
    throw new RuntimeException(
        'Identificador de autorizacao invalido.'
    );
}

$suffix = $matches[1];

$purchase = Purchase::query()
    ->where('authorization_network_id', $authorizationId)
    ->firstOrFail();

$card = $purchase->card;

if (
    $card === null
    || $card->card_token !== "tok_audit_{$suffix}"
    || $card->company->name !== "Auditoria Concorrencia {$suffix}"
    || $card->user->email !== "audit_{$suffix}@example.test"
) {
    throw new RuntimeException(
        'Compra nao pertence ao cenario de auditoria.'
    );
}

DB::beginTransaction();

try {
    Purchase::query()
        ->whereKey($purchase->id)
        ->lockForUpdate()
        ->firstOrFail();

    echo 'LOCK_ACQUIRED'.PHP_EOL;
    flush();

    // Segura o bloqueio por no maximo 5 segundos.
    Sleep::sleep(5);
} finally {
    DB::rollBack();

    echo 'LOCK_RELEASED'.PHP_EOL;
}
