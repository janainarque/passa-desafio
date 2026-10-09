<?php

declare(strict_types=1);

use App\Models\Company;
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

function signedP3Request(
    string $method,
    string $uri,
    array $payload = [],
) {
    $timestamp = (string) Date::now()->getTimestamp();

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

it('reproduz o cenario publicado P3', function (): void {
    $baseTime = now('UTC');

    signedP3Request(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'p3_aut_400',
            'card_token' => 'tok_bruno',
            'amount_cents' => 40000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante P3',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => $baseTime
                ->format('Y-m-d\TH:i:s\Z'),
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedP3Request(
        'GET',
        '/api/network/cards/tok_bruno/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 10000,
            'limit_remaining_cents' => 10000,
        ]);

    signedP3Request(
        'POST',
        '/api/network/events',
        [
            'id' => 'p3_capture_480',
            'type' => 'capture',
            'occurred_at' => $baseTime
                ->addMinute()
                ->format('Y-m-d\TH:i:s\Z'),
            'authorization_id' => 'p3_aut_400',
            'amount_cents' => 48000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    signedP3Request(
        'GET',
        '/api/network/cards/tok_bruno/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 2000,
            'limit_remaining_cents' => 2000,
        ]);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'p3_aut_400')
        ->firstOrFail();

    $this->assertDatabaseMissing('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'overcapture_exceeded',
    ]);

    signedP3Request(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'p3_aut_50',
            'card_token' => 'tok_bruno',
            'amount_cents' => 5000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante P3 2',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => $baseTime
                ->addMinutes(2)
                ->format('Y-m-d\TH:i:s\Z'),
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'monthly_limit_exceeded',
        ]);

    signedP3Request(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'p3_aut_20',
            'card_token' => 'tok_bruno',
            'amount_cents' => 2000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante P3 3',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => $baseTime
                ->addMinutes(3)
                ->format('Y-m-d\TH:i:s\Z'),
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedP3Request(
        'GET',
        '/api/network/cards/tok_bruno/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 0,
            'limit_remaining_cents' => 0,
        ]);

    signedP3Request(
        'GET',
        '/api/network/cards/tok_diego/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 950000,
            'limit_remaining_cents' => 5000000,
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(952000)
        ->and($company->reserved_cents)
        ->toBe(2000);

    $month = now('America/Sao_Paulo')
        ->format('Y-m');

    signedP3Request(
        'GET',
        "/api/network/cards/tok_bruno/statement?month={$month}",
    )
        ->assertOk()
        ->assertJsonPath(
            'limit_remaining_cents',
            0,
        );
});
