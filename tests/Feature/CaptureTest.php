<?php

declare(strict_types=1);

use App\Models\Capture;
use App\Models\Card;
use App\Models\CardMonthBalance;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
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

function signedNetworkRequest(string $method, string $uri, array $payload)
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

it('processa capture parcial mantendo o total comprometido do cartao', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_capture_partial',
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
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_partial',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_capture_partial',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(970000)
        ->and($company->reserved_cents)
        ->toBe(50000);

    $card = Card::query()->where('card_token', 'tok_ana')->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)->toBe(120000);

    $this->assertDatabaseHas('captures', [
        'network_id' => 'evt_capture_partial',
        'sequence' => 1,
        'amount_cents' => 30000,
        'final' => false,
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_capture_partial',
        'type' => 'capture_settlement',
        'card_limit_delta_cents' => 0,
        'company_balance_delta_cents' => -30000,
        'company_reserved_delta_cents' => -30000,
    ]);
});

it('processa capture final e libera a reserva restante', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_capture_final',
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
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_final',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_capture_final',
            'amount_cents' => 60000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(940000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(140000);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_capture_final',
        'type' => 'capture_settlement',
        'card_limit_delta_cents' => 20000,
        'company_balance_delta_cents' => -60000,
        'company_reserved_delta_cents' => -80000,
    ]);
});

it('aceita captura total ate vinte por cento acima em mcc permitido', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_hotel',
            'card_token' => 'tok_ana',
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'mcc' => '7011',
            'merchant' => [
                'name' => 'Hotel Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T01:00:00Z',
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_hotel_1',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_hotel',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_hotel_2',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_hotel',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 2,
            'final' => false,
        ],
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_hotel_3',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:30:00Z',
            'authorization_id' => 'aut_hotel',
            'amount_cents' => 26000,
            'currency' => 'BRL',
            'sequence' => 3,
            'final' => true,
        ],
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(914000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(114000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_hotel')
        ->firstOrFail();

    $this->assertDatabaseMissing('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'overcapture_exceeded',
    ]);
});

it('registra issue quando captura ultrapassa o valor esperado', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_overcapture',
            'card_token' => 'tok_ana',
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'mcc' => '7011',
            'merchant' => [
                'name' => 'Hotel Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T01:00:00Z',
        ],
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_overcapture',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_overcapture',
            'amount_cents' => 97000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    $this->assertDatabaseHas('purchase_issues', [
        'code' => 'overcapture_exceeded',
        'related_network_id' => 'evt_overcapture',
    ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(903000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(103000);
});

it('guarda capture como pendente quando authorization ainda nao chegou', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_before_authorization',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:00:00Z',
            'authorization_id' => 'aut_late',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertSuccessful();

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_late')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('pending_authorization')
        ->and($purchase->card_id)
        ->toBeNull()
        ->and($purchase->month)
        ->toBeNull();

    $this->assertDatabaseHas('captures', [
        'network_id' => 'evt_before_authorization',
        'purchase_id' => $purchase->id,
        'amount_cents' => 30000,
        'sequence' => 1,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'evt_before_authorization',
    ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(1000000)
        ->and($company->reserved_cents)
        ->toBe(0);
});

it('reconcilia capture pendente quando authorization chega depois', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_pending_capture',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_after_capture',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertSuccessful();

    $companyBefore = Company::query()->firstOrFail();

    expect($companyBefore->balance_cents)
        ->toBe(1000000)
        ->and($companyBefore->reserved_cents)
        ->toBe(0);

    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_after_capture',
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
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(970000)
        ->and($company->reserved_cents)
        ->toBe(50000);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(120000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_after_capture')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('partially_captured')
        ->and($purchase->authorized_amount_cents)
        ->toBe(80000)
        ->and($purchase->captured_amount_cents)
        ->toBe(30000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(50000);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'aut_after_capture',
        'type' => 'authorization_hold',
        'card_limit_delta_cents' => -80000,
        'company_reserved_delta_cents' => 80000,
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_pending_capture',
        'type' => 'capture_settlement',
        'card_limit_delta_cents' => 0,
        'company_balance_delta_cents' => -30000,
        'company_reserved_delta_cents' => -30000,
    ]);
});

it('reconcilia capture pendente quando authorization chega recusada', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_pending_declined',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_declined_after_capture',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertSuccessful();

    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_declined_after_capture',
            'card_token' => 'tok_ana',
            'amount_cents' => 90000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T01:00:00Z',
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'declined',
            'reason' => 'amount_over_purchase_limit',
        ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(970000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(170000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_declined_after_capture')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('declined')
        ->and($purchase->captured_amount_cents)
        ->toBe(30000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0);

    $this->assertDatabaseHas('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'capture_for_declined_authorization',
        'related_network_id' => 'evt_pending_declined',
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_pending_declined',
        'type' => 'capture_settlement',
        'card_limit_delta_cents' => -30000,
        'company_balance_delta_cents' => -30000,
        'company_reserved_delta_cents' => 0,
    ]);
});

it('nao processa a mesma capture duas vezes quando o id da rede se repete', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_duplicate_capture',
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
    )->assertOk();

    $capturePayload = [
        'id' => 'evt_duplicate_capture',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T01:10:00Z',
        'authorization_id' => 'aut_duplicate_capture',
        'amount_cents' => 30000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
    ];

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        $capturePayload,
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        $capturePayload,
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(970000)
        ->and($company->reserved_cents)
        ->toBe(50000);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(120000);

    expect(
        Capture::query()
            ->where('network_id', 'evt_duplicate_capture')
            ->count()
    )->toBe(1);

    expect(
        Transaction::query()
            ->where('reference', 'evt_duplicate_capture')
            ->count()
    )->toBe(1);
});

it('nao processa novamente capture reemitida com novo id e mesma sequence', function (): void {
    signedNetworkRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_reissued_capture',
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
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_reissued_capture_1',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_reissued_capture',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    signedNetworkRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_reissued_capture_2',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_reissued_capture',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(970000)
        ->and($company->reserved_cents)
        ->toBe(50000);

    $card = Card::query()
        ->where('card_token', 'tok_ana')
        ->firstOrFail();

    $monthBalance = CardMonthBalance::query()
        ->where('card_id', $card->id)
        ->where('month', '2026-10')
        ->firstOrFail();

    expect($monthBalance->limit_remaining_cents)
        ->toBe(120000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_reissued_capture')
        ->firstOrFail();

    expect(
        Capture::query()
            ->where('purchase_id', $purchase->id)
            ->where('sequence', 1)
            ->count()
    )->toBe(1);

    expect(
        Transaction::query()
            ->where('purchase_id', $purchase->id)
            ->where('type', 'capture_settlement')
            ->count()
    )->toBe(1);
});
