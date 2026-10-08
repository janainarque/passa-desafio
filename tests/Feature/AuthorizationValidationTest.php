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

function signedAuthorizationRequest(array $payload): array
{
    $timestamp = (string) time();

    $body = json_encode(
        $payload,
        JSON_THROW_ON_ERROR
    );

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret'
    );

    return [
        'body' => $body,
        'headers' => [
            'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
            'HTTP_X_NETWORK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
    ];
}

it('aceita payload valido de authorization', function (): void {
    $request = signedAuthorizationRequest([
        'id' => 'aut_001',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response = $this->call(
        'POST',
        '/api/network/authorizations',
        [],
        [],
        [],
        $request['headers'],
        $request['body']
    );

    $response->assertOk()->assertJson(['decision' => 'approved']);
});

it('rejeita amount cents enviado como string', function (): void {
    $request = signedAuthorizationRequest([
        'id' => 'aut_002',
        'card_token' => 'tok_ana',
        'amount_cents' => '12990',
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response = $this->call(
        'POST',
        '/api/network/authorizations',
        [],
        [],
        [],
        $request['headers'],
        $request['body']
    );

    $response->assertUnprocessable();
});

it('rejeita currency diferente de BRL', function (): void {
    $request = signedAuthorizationRequest([
        'id' => 'aut_003',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'USD',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response = $this->call(
        'POST',
        '/api/network/authorizations',
        [],
        [],
        [],
        $request['headers'],
        $request['body']
    );

    $response->assertUnprocessable();
});

it('rejeita merchant incompleto', function (): void {
    $request = signedAuthorizationRequest([
        'id' => 'aut_004',
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Bom Prato',
            'city' => 'Porto Alegre',
        ],
        'occurred_at' => '2026-09-17T14:03:22Z',
    ]);

    $response = $this->call(
        'POST',
        '/api/network/authorizations',
        [],
        [],
        [],
        $request['headers'],
        $request['body']
    );

    $response->assertUnprocessable();
});
