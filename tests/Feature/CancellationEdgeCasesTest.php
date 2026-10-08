<?php

declare(strict_types=1);

use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set(
        'services.network.secret',
        'test-network-secret',
    );

    $this->seed();
});

function signedCancellationEdgeRequest(
    string $uri,
    array $payload,
) {
    $timestamp = (string) time();

    $body = json_encode(
        $payload,
        JSON_THROW_ON_ERROR,
    );

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    return test()->call(
        'POST',
        $uri,
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
}

it('processa cancellation apos autorizacao recusada sem saldo mensal criado', function (): void {
    signedCancellationEdgeRequest(
        '/api/network/authorizations',
        [
            'id' => 'cancel_declined_blocked',
            'card_token' => 'tok_carla',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Loja Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T04:00:00Z',
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'card_blocked',
        ]);

    signedCancellationEdgeRequest(
        '/api/network/events',
        [
            'id' => 'cancel_declined_event',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T04:01:00Z',
            'authorization_id' => 'cancel_declined_blocked',
        ],
    )->assertOk();

    $purchase = Purchase::query()
        ->where(
            'authorization_network_id',
            'cancel_declined_blocked',
        )
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('canceled')
        ->and($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'cancel_declined_event',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'cancel_declined_event',
    ]);
});
