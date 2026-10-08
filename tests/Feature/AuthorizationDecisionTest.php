<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Company;
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

function sendAuthorization(array $payload)
{
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
    );
}

function authorizationPayload(array $overrides = []): array
{
    return array_merge([
        'id' => 'aut_test',
        'card_token' => 'tok_ana',
        'amount_cents' => 10000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => [
            'name' => 'Restaurante Teste',
            'city' => 'Brasilia',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-10-07T15:00:00Z',
    ], $overrides);
}

it('recusa quando o cartao nao existe', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_card_not_found',
            'card_token' => 'tok_inexistente',
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'card_not_found',
        ]);
});

it('recusa quando o cartao esta bloqueado', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_card_blocked',
            'card_token' => 'tok_carla',
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'card_blocked',
        ]);
});

it('recusa quando o mcc esta bloqueado', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_mcc_blocked',
            'card_token' => 'tok_ana',
            'mcc' => '7995',
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'mcc_blocked',
        ]);
});

it('recusa quando ultrapassa o limite por compra', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_purchase_limit',
            'card_token' => 'tok_ana',
            'amount_cents' => 80001,
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'amount_over_purchase_limit',
        ]);
});

it('recusa quando ultrapassa o limite mensal', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_monthly_limit',
            'card_token' => 'tok_bruno',
            'amount_cents' => 50001,
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'monthly_limit_exceeded',
        ]);
});

it('recusa quando a empresa nao tem saldo disponivel', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_insufficient_funds',
            'card_token' => 'tok_diego',
            'amount_cents' => 1000001,
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'insufficient_funds',
        ]);
});

it('aprova valor igual ao limite por compra e reserva o valor', function (): void {
    $response = sendAuthorization(
        authorizationPayload([
            'id' => 'aut_approved',
            'card_token' => 'tok_ana',
            'amount_cents' => 80000,
        ]),
    );

    $response
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(1000000)
        ->and($company->reserved_cents)
        ->toBe(80000);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(120000);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'aut_approved',
        'type' => 'authorization_hold',
        'card_limit_delta_cents' => -80000,
        'company_balance_delta_cents' => 0,
        'company_reserved_delta_cents' => 80000,
    ]);
});

it('nao processa a mesma authorization duas vezes', function (): void {
    $payload = authorizationPayload([
        'id' => 'aut_idempotent',
        'card_token' => 'tok_ana',
        'amount_cents' => 50000,
    ]);

    sendAuthorization($payload)
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    sendAuthorization($payload)
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->reserved_cents)->toBe(50000);

    $card = Card::query()->where('card_token', 'tok_ana')->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)->toBe(150000);

    $this->assertDatabaseCount('authorizations', 1);

    $this->assertDatabaseCount('transactions', 2);
});
