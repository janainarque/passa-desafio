<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set(
        'services.network.secret',
        'test-network-secret',
    );

    $this->seed();
});

function signedEventValidationRequest(array $payload)
{
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
        '/api/network/events',
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

it('ignora campos extras de capture em cancellation', function (): void {
    signedEventValidationRequest([
        'id' => 'cancel_extra_fields',
        'type' => 'cancellation',
        'occurred_at' => '2026-10-08T04:30:00Z',
        'authorization_id' => 'auth_ainda_nao_recebida',

        'amount_cents' => 'isso deve ser ignorado',
        'currency' => 123,
        'sequence' => 'invalido',
        'final' => 'invalido',
    ])
        ->assertOk();

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'cancel_extra_fields',
    ]);
});

it('exige campos de capture quando o evento e capture', function (): void {
    signedEventValidationRequest([
        'id' => 'capture_sem_campos',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T04:31:00Z',
        'authorization_id' => 'auth_teste',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'amount_cents',
            'currency',
            'sequence',
            'final',
        ]);
});
