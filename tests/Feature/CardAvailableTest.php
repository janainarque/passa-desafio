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

function signedAvailableRequest(string $uri)
{
    $timestamp = (string) time();

    $body = '';

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    return test()->call(
        'GET',
        $uri,
        [],
        [],
        [],
        [
            'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
            'HTTP_X_NETWORK_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ],
    );
}

it('retorna o disponivel atual do cartao', function (): void {
    signedAvailableRequest(
        '/api/network/cards/tok_ana/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 200000,
            'limit_remaining_cents' => 200000,
        ]);
});

it('retorna 404 para cartao inexistente', function (): void {
    signedAvailableRequest(
        '/api/network/cards/token_inexistente/available',
    )->assertNotFound();
});

it('retorna disponivel zero para cartao bloqueado', function (): void {
    signedAvailableRequest(
        '/api/network/cards/tok_carla/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 0,
            'limit_remaining_cents' => 100000,
        ]);
});

it('limita o disponivel pelo saldo disponivel da empresa', function (): void {
    signedAvailableRequest(
        '/api/network/cards/tok_diego/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 1000000,
            'limit_remaining_cents' => 5000000,
        ]);
});

function signedAvailablePostRequest(string $uri, array $payload)
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

it('mantem available e statement consistentes no mes atual', function (): void {
    signedAvailablePostRequest(
        '/api/network/authorizations',
        [
            'id' => 'aut_available_statement',
            'card_token' => 'tok_ana',
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $availableResponse = signedAvailableRequest(
        '/api/network/cards/tok_ana/available',
    )->assertOk();

    $month = now('America/Sao_Paulo')->format('Y-m');

    $statementResponse = signedAvailableRequest(
        "/api/network/cards/tok_ana/statement?month={$month}",
    )->assertOk();

    expect(
        $availableResponse->json('limit_remaining_cents')
    )->toBe(
        $statementResponse->json('limit_remaining_cents')
    );

    expect(
        $availableResponse->json('limit_remaining_cents')
    )->toBe(120000);
});
