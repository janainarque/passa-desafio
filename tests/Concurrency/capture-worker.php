<?php

declare(strict_types=1);

use App\Models\Purchase;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/guard.php';

config()->set('services.network.secret', 'test-network-secret');

if (! isset($argv[1])) {
    throw new RuntimeException('Payload da captura nao informado.');
}

$payload = json_decode(
    base64_decode($argv[1], true),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

if (! is_array($payload)) {
    throw new RuntimeException('Payload invalido.');
}

// Restringir o worker aos identificadores da auditoria.
if (
    ($payload['type'] ?? null) !== 'capture'
    || ! is_string($payload['id'] ?? null)
    || ! is_string($payload['authorization_id'] ?? null)
    || ! preg_match(
        '/^audit_cap_[a-f0-9]{12}$/',
        $payload['id'],
    )
    || ! preg_match(
        '/^audit_auth_([a-f0-9]{12})$/',
        $payload['authorization_id'],
        $matches,
    )
) {
    throw new RuntimeException('Captura fora do cenario de auditoria.');
}

$suffix = $matches[1];

$purchase = Purchase::query()
    ->where('authorization_network_id', $payload['authorization_id'])
    ->firstOrFail();

$card = $purchase->card;

if (
    $card === null
    || $card->card_token !== "tok_audit_{$suffix}"
    || $card->company->name !== "Auditoria Concorrencia {$suffix}"
    || $card->user->email !== "audit_{$suffix}@example.test"
) {
    throw new RuntimeException('Compra nao pertence ao cenario.');
}

$body = json_encode($payload, JSON_THROW_ON_ERROR);
$timestamp = (string) now()->getTimestamp();

$signature = 'sha256='.hash_hmac(
    'sha256',
    $timestamp.'.'.$body,
    'test-network-secret',
);

$request = Request::create(
    '/api/network/events',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
        'HTTP_X_NETWORK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ],
    $body,
);

$kernel = resolve(Kernel::class);

$response = $kernel->handle($request);
$kernel->terminate($request, $response);

echo PHP_EOL.'CAPTURE_RESULT='.json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR).PHP_EOL;
