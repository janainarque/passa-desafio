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

function signedP2Request(
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

it('reproduz o cenario publicado P2', function (): void {
    $baseTime = now('UTC');

    signedP2Request(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'p2_aut',
            'card_token' => 'tok_ana',
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'mcc' => '7011',
            'merchant' => [
                'name' => 'Hotel P2',
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

    signedP2Request(
        'GET',
        '/api/network/cards/tok_ana/available',
    )->assertJson([
        'available_cents' => 120000,
        'limit_remaining_cents' => 120000,
    ]);

    signedP2Request(
        'GET',
        '/api/network/cards/tok_diego/available',
    )->assertJson([
        'available_cents' => 920000,
        'limit_remaining_cents' => 5000000,
    ]);

    signedP2Request(
        'POST',
        '/api/network/events',
        [
            'id' => 'p2_capture_1',
            'type' => 'capture',
            'occurred_at' => $baseTime
                ->addMinute()
                ->format('Y-m-d\TH:i:s\Z'),
            'authorization_id' => 'p2_aut',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    signedP2Request(
        'POST',
        '/api/network/events',
        [
            'id' => 'p2_capture_2',
            'type' => 'capture',
            'occurred_at' => $baseTime
                ->addMinutes(2)
                ->format('Y-m-d\TH:i:s\Z'),
            'authorization_id' => 'p2_aut',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 2,
            'final' => false,
        ],
    )->assertOk();

    signedP2Request(
        'POST',
        '/api/network/events',
        [
            'id' => 'p2_capture_3',
            'type' => 'capture',
            'occurred_at' => $baseTime
                ->addMinutes(3)
                ->format('Y-m-d\TH:i:s\Z'),
            'authorization_id' => 'p2_aut',
            'amount_cents' => 26000,
            'currency' => 'BRL',
            'sequence' => 3,
            'final' => true,
        ],
    )->assertOk();

    signedP2Request(
        'GET',
        '/api/network/cards/tok_ana/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 114000,
            'limit_remaining_cents' => 114000,
        ]);

    signedP2Request(
        'GET',
        '/api/network/cards/tok_diego/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 914000,
            'limit_remaining_cents' => 5000000,
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(914000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'p2_aut')
        ->firstOrFail();

    expect($purchase->captured_amount_cents)
        ->toBe(86000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0);

    $this->assertDatabaseMissing('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'overcapture_exceeded',
    ]);

    $month = now('America/Sao_Paulo')
        ->format('Y-m');

    signedP2Request(
        'GET',
        "/api/network/cards/tok_ana/statement?month={$month}",
    )
        ->assertOk()
        ->assertJsonPath(
            'limit_remaining_cents',
            114000,
        );
});
