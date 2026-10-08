<?php

declare(strict_types=1);

use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set(
        'services.network.secret',
        'test-network-secret',
    );

    $this->seed();
});

function signedCaptureEdgeRequest(
    string $uri,
    array $payload,
) {
    $timestamp = (string) Date::now()->getTimestamp();

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

it('processa capture apos autorizacao recusada sem saldo mensal criado', function (): void {
    signedCaptureEdgeRequest(
        '/api/network/authorizations',
        [
            'id' => 'edge_declined_blocked',
            'card_token' => 'tok_carla',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Loja Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T03:00:00Z',
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'card_blocked',
        ]);

    signedCaptureEdgeRequest(
        '/api/network/events',
        [
            'id' => 'edge_capture_declined',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T03:01:00Z',
            'authorization_id' => 'edge_declined_blocked',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    $this->assertDatabaseHas('purchase_issues', [
        'code' => 'capture_for_declined_authorization',
    ]);

    $this->assertDatabaseHas('card_month_balances', [
        'month' => '2026-10',
        'limit_remaining_cents' => 90000,
    ]);
});

it('mantem compra settled se chegar capture depois do final', function (): void {
    signedCaptureEdgeRequest(
        '/api/network/authorizations',
        [
            'id' => 'edge_final_auth',
            'card_token' => 'tok_ana',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Loja Final',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T03:10:00Z',
        ],
    )->assertOk();

    signedCaptureEdgeRequest(
        '/api/network/events',
        [
            'id' => 'edge_final_capture',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T03:11:00Z',
            'authorization_id' => 'edge_final_auth',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    signedCaptureEdgeRequest(
        '/api/network/events',
        [
            'id' => 'edge_late_capture',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T03:12:00Z',
            'authorization_id' => 'edge_final_auth',
            'amount_cents' => 1000,
            'currency' => 'BRL',
            'sequence' => 2,
            'final' => false,
        ],
    )->assertOk();

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'edge_final_auth')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('settled')
        ->and($purchase->reserved_amount_cents)
        ->toBe(0);
});
