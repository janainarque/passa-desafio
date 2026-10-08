<?php

declare(strict_types=1);

use App\Models\Company;
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

function signedDeclinedCaptureRequest(
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

it('aceita capture de authorization recusada e sinaliza problema', function (): void {
    $occurredAt = now('UTC');

    signedDeclinedCaptureRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_declined_capture',
            'card_token' => 'tok_bruno',
            'amount_cents' => 60000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => $occurredAt
                ->format('Y-m-d\TH:i:s\Z'),
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'monthly_limit_exceeded',
        ]);

    signedDeclinedCaptureRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'capture_after_decline',
            'type' => 'capture',
            'occurred_at' => $occurredAt
                ->addMinute()
                ->format('Y-m-d\TH:i:s\Z'),
            'authorization_id' => 'aut_declined_capture',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    signedDeclinedCaptureRequest(
        'GET',
        '/api/network/cards/tok_bruno/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 40000,
            'limit_remaining_cents' => 40000,
        ]);

    signedDeclinedCaptureRequest(
        'GET',
        '/api/network/cards/tok_diego/available',
    )
        ->assertOk()
        ->assertJson([
            'available_cents' => 990000,
            'limit_remaining_cents' => 5000000,
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(990000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $purchase = Purchase::query()
        ->where(
            'authorization_network_id',
            'aut_declined_capture',
        )
        ->firstOrFail();

    expect($purchase->captured_amount_cents)
        ->toBe(10000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0);

    $this->assertDatabaseHas('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'capture_for_declined_authorization',
    ]);
});
