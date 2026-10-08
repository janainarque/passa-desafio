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

function signedP1Request(
    string $method,
    string $uri,
    array $payload = [],
) {
    $timestamp = (string) time();

    $body = $method === 'GET'
        ? ''
        : json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        );

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    return test()->call(
        $method,
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

it('reproduz o cenario publicado P1', function (): void {
    $amounts = [
        12990,
        4500,
        30000,
        8010,
        1500,
    ];

    $occurredAt = now('UTC');

    foreach ($amounts as $index => $amount) {
        $number = $index + 1;

        signedP1Request(
            'POST',
            '/api/network/authorizations',
            [
                'id' => "p1_aut_{$number}",
                'card_token' => 'tok_ana',
                'amount_cents' => $amount,
                'currency' => 'BRL',
                'mcc' => '5812',
                'merchant' => [
                    'name' => "Restaurante P1 {$number}",
                    'city' => 'Brasilia',
                    'country' => 'BR',
                ],
                'occurred_at' => $occurredAt
                    ->addMinutes($index * 2)
                    ->format('Y-m-d\TH:i:s\Z'),
            ],
        )
            ->assertOk()
            ->assertJson([
                'decision' => 'approved',
            ]);

        signedP1Request(
            'POST',
            '/api/network/events',
            [
                'id' => "p1_capture_{$number}",
                'type' => 'capture',
                'occurred_at' => $occurredAt
                    ->addMinutes(($index * 2) + 1)
                    ->format('Y-m-d\TH:i:s\Z'),
                'authorization_id' => "p1_aut_{$number}",
                'amount_cents' => $amount,
                'currency' => 'BRL',
                'sequence' => 1,
                'final' => true,
            ],
        )->assertOk();
    }

    signedP1Request(
        'GET',
        '/api/network/cards/tok_ana/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 143000,
            'limit_remaining_cents' => 143000,
        ]);

    signedP1Request(
        'GET',
        '/api/network/cards/tok_diego/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 943000,
            'limit_remaining_cents' => 5000000,
        ]);

    $month = now('America/Sao_Paulo')
        ->format('Y-m');

    signedP1Request(
        'GET',
        "/api/network/cards/tok_ana/statement?month={$month}",
    )
        ->assertOk()
        ->assertJsonPath(
            'limit_remaining_cents',
            143000,
        );
});
