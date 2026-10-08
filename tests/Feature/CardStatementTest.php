<?php

declare(strict_types=1);

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

function signedStatementRequest(string $uri)
{
    $timestamp = (string) Date::now()->getTimestamp();
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

it('retorna statement vazio de um mes valido', function (): void {
    signedStatementRequest(
        '/api/network/cards/tok_ana/statement?month=2026-09',
    )
        ->assertOk()
        ->assertJson([
            'month' => '2026-09',
            'limit_cents' => 200000,
            'limit_remaining_cents' => 200000,
            'transactions' => [],
        ]);
});

it('monta o statement com saldo restante apos cada transaction', function (): void {
    $authorizationPayload = [
        'id' => 'aut_statement',
        'card_token' => 'tok_ana',
        'amount_cents' => 80000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Teste',
            'city' => 'Brasilia',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-10-08T01:00:00Z',
    ];

    $timestamp = (string) Date::now()->getTimestamp();

    $body = json_encode(
        $authorizationPayload,
        JSON_THROW_ON_ERROR,
    );

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    test()->call(
        'POST',
        '/api/network/authorizations',
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
    )->assertOk();

    $capturePayload = [
        'id' => 'evt_statement_capture',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T01:10:00Z',
        'authorization_id' => 'aut_statement',
        'amount_cents' => 30000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
    ];

    $timestamp = (string) Date::now()->getTimestamp();

    $body = json_encode(
        $capturePayload,
        JSON_THROW_ON_ERROR,
    );

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    test()->call(
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
    )->assertOk();

    signedStatementRequest(
        '/api/network/cards/tok_ana/statement?month=2026-10',
    )
        ->assertOk()
        ->assertJson([
            'month' => '2026-10',
            'limit_cents' => 200000,
            'limit_remaining_cents' => 120000,
            'transactions' => [
                [
                    'occurred_at' => '2026-10-08T01:00:00Z',
                    'type' => 'authorization_hold',
                    'amount_cents' => -80000,
                    'reference' => 'aut_statement',
                    'limit_remaining_after_cents' => 120000,
                ],
                [
                    'occurred_at' => '2026-10-08T01:10:00Z',
                    'type' => 'capture_settlement',
                    'amount_cents' => 0,
                    'reference' => 'evt_statement_capture',
                    'limit_remaining_after_cents' => 120000,
                ],
            ],
        ]);
});

it('retorna 422 para month invalido', function (): void {
    signedStatementRequest(
        '/api/network/cards/tok_ana/statement?month=2026-13',
    )->assertUnprocessable();
});

it('retorna 404 para cartao inexistente no statement', function (): void {
    signedStatementRequest(
        '/api/network/cards/token_inexistente/statement?month=2026-10',
    )->assertNotFound();
});

it('usa o mes atual quando month nao e informado', function (): void {
    signedStatementRequest(
        '/api/network/cards/tok_ana/statement',
    )
        ->assertOk()
        ->assertJsonPath(
            'month',
            now('America/Sao_Paulo')->format('Y-m'),
        );
});
