<?php

declare(strict_types=1);

use App\Models\Card;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/guard.php';

config()->set('services.network.secret', 'test-network-secret');

if (! isset($argv[1])) {
    throw new RuntimeException('Payload da autorizacao nao informado.');
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

if (
    ! is_string($payload['id'] ?? null)
    || ! preg_match('/^audit_concurrent_auth_([a-f0-9]{12})_[12]$/', $payload['id'], $matches)
    || ! is_string($payload['card_token'] ?? null)
    || ! preg_match('/^tok_concurrent_([a-f0-9]{12})_[12]$/', $payload['card_token'], $cardMatches)
    || $matches[1] !== $cardMatches[1]
    || ($payload['amount_cents'] ?? null) !== 80000
    || ($payload['currency'] ?? null) !== 'BRL'
    || ($payload['mcc'] ?? null) !== '5812'
) {
    throw new RuntimeException('Autorizacao fora do cenario de auditoria.');
}

$suffix = $matches[1];

$card = Card::query()
    ->with(['company', 'user'])
    ->where('card_token', $payload['card_token'])
    ->firstOrFail();

if (
    $card->company === null
    || $card->company->name !== "Auditoria Autorizacoes {$suffix}"
    || $card->user === null
    || ! preg_match(
        '/^audit_concurrent_'.$suffix.'_[12]@example\.test$/',
        $card->user->email,
    )
) {
    throw new RuntimeException('Cartao nao pertence ao cenario.');
}

$body = json_encode($payload, JSON_THROW_ON_ERROR);
$timestamp = (string) now()->getTimestamp();

$signature = 'sha256='.hash_hmac(
    'sha256',
    $timestamp.'.'.$body,
    'test-network-secret',
);

$request = Request::create(
    '/api/network/authorizations',
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

echo PHP_EOL.'AUTHORIZATION_RESULT='.json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR).PHP_EOL;
