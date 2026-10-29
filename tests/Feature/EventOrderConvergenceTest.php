<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.network.secret', 'test-network-secret');
    $this->seed();
});

function signedEventOrderRequest(string $uri, array $payload)
{
    $timestamp = (string) Date::now()->getTimestamp();
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    return test()->call('POST', $uri, [], [], [], [
        'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
        'HTTP_X_NETWORK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

it('mantem os mesmos saldos independentemente da ordem de chegada', function (array $order): void {
    $authorizationId = 'aut_order_convergence';

    $messages = [
        'authorization' => [
            'id' => $authorizationId,
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
        ],
        'capture' => [
            'id' => 'evt_order_capture',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => $authorizationId,
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
        'cancellation' => [
            'id' => 'evt_order_cancellation',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => $authorizationId,
        ],
    ];

    foreach ($order as $type) {
        $uri = $type === 'authorization'
            ? '/api/network/authorizations'
            : '/api/network/events';

        $response = signedEventOrderRequest($uri, $messages[$type]);

        $response->assertSuccessful();

        if ($type === 'authorization') {
            $response->assertJson(['decision' => 'approved']);
        }
    }

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)->toBe(970000)
        ->and($company->reserved_cents)->toBe(0);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)->toBe(170000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', $authorizationId)
        ->firstOrFail();

    expect($purchase->status)->toBe('canceled')
        ->and($purchase->captured_amount_cents)->toBe(30000)
        ->and($purchase->reserved_amount_cents)->toBe(0)
        ->and($purchase->has_cancellation)->toBeTrue();

    expect(
        Transaction::query()
            ->where('reference', 'evt_order_capture')
            ->where('type', 'capture_settlement')
            ->count()
    )->toBe(1);

    expect(
        Transaction::query()
            ->where('reference', 'evt_order_cancellation')
            ->where('type', 'cancellation_release')
            ->count()
    )->toBe(1);
})->with([
    'autorizacao captura cancelamento' => [
        ['authorization', 'capture', 'cancellation'],
    ],
    'autorizacao cancelamento captura' => [
        ['authorization', 'cancellation', 'capture'],
    ],
    'captura autorizacao cancelamento' => [
        ['capture', 'authorization', 'cancellation'],
    ],
    'captura cancelamento autorizacao' => [
        ['capture', 'cancellation', 'authorization'],
    ],
    'cancelamento autorizacao captura' => [
        ['cancellation', 'authorization', 'capture'],
    ],
    'cancelamento captura autorizacao' => [
        ['cancellation', 'capture', 'authorization'],
    ],
]);
