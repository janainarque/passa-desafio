<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\CardMonthBalance;
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

function signedCancellationRequest(
    string $method,
    string $uri,
    array $payload,
) {
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

it('cancela a parte ainda reservada da compra', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_cancel',
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

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_before_cancel',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_cancel',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_cancel',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_cancel',
        ],
    )->assertOk();

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
        ->where('authorization_network_id', 'aut_cancel')
        ->firstOrFail();

    expect($purchase->captured_amount_cents)
        ->toBe(30000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->has_cancellation)
        ->toBeTrue()
        ->and($purchase->status)
        ->toBe('canceled');

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'evt_cancel',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_cancel',
        'type' => 'cancellation_release',
        'card_limit_delta_cents' => 50000,
        'company_balance_delta_cents' => 0,
        'company_reserved_delta_cents' => -50000,
    ]);
});

it('processa capture depois de cancellation e registra issue', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_capture_after_cancel',
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

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_before_cancel_2',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_capture_after_cancel',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertOk();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_cancel_2',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_capture_after_cancel',
        ],
    )->assertOk();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_after_cancel',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:30:00Z',
            'authorization_id' => 'aut_capture_after_cancel',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'sequence' => 2,
            'final' => true,
        ],
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(960000)
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
        ->toBe(160000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_capture_after_cancel')
        ->firstOrFail();

    expect($purchase->captured_amount_cents)
        ->toBe(40000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->status)
        ->toBe('canceled');

    $this->assertDatabaseHas('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'capture_after_cancellation',
        'related_network_id' => 'evt_capture_after_cancel',
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_capture_after_cancel',
        'type' => 'capture_settlement',
        'card_limit_delta_cents' => -10000,
        'company_balance_delta_cents' => -10000,
        'company_reserved_delta_cents' => 0,
    ]);
});

it('guarda cancellation como pendente quando authorization ainda nao chegou', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_cancel_before_auth',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_after_cancel',
        ],
    )->assertSuccessful();

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_after_cancel')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('pending_authorization')
        ->and($purchase->card_id)
        ->toBeNull()
        ->and($purchase->month)
        ->toBeNull()
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'evt_cancel_before_auth',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'evt_cancel_before_auth',
    ]);

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(1000000)
        ->and($company->reserved_cents)
        ->toBe(0);
});

it('reconcilia cancellation pendente quando authorization chega depois', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_pending_cancel',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_after_pending_cancel',
        ],
    )->assertSuccessful();

    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_after_pending_cancel',
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
        ->toBe(1000000)
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
        ->toBe(200000);

    $purchase = Purchase::query()
        ->where(
            'authorization_network_id',
            'aut_after_pending_cancel'
        )
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('canceled')
        ->and($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->captured_amount_cents)
        ->toBe(0)
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('transactions', [
        'reference' => 'aut_after_pending_cancel',
        'type' => 'authorization_hold',
        'card_limit_delta_cents' => -80000,
        'company_reserved_delta_cents' => 80000,
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_pending_cancel',
        'type' => 'cancellation_release',
        'card_limit_delta_cents' => 80000,
        'company_balance_delta_cents' => 0,
        'company_reserved_delta_cents' => -80000,
    ]);
});

it('guarda capture e cancellation quando ambos chegam antes da authorization', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_pending_both',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_pending_both',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertSuccessful();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_cancel_pending_both',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_pending_both',
        ],
    )->assertSuccessful();

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_pending_both')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('pending_authorization')
        ->and($purchase->card_id)
        ->toBeNull()
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('captures', [
        'network_id' => 'evt_capture_pending_both',
        'purchase_id' => $purchase->id,
        'amount_cents' => 30000,
    ]);

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'evt_cancel_pending_both',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'evt_capture_pending_both',
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'evt_cancel_pending_both',
    ]);
});

it('converge financeiramente quando capture e cancellation chegam antes da authorization', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_capture_before_all',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_last',
            'amount_cents' => 30000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => false,
        ],
    )->assertSuccessful();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'evt_cancel_before_auth_last',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_last',
        ],
    )->assertSuccessful();

    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_last',
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
        ->where('authorization_network_id', 'aut_last')
        ->firstOrFail();

    expect($purchase->status)
        ->toBe('canceled')
        ->and($purchase->captured_amount_cents)
        ->toBe(30000)
        ->and($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_capture_before_all',
        'type' => 'capture_settlement',
        'company_balance_delta_cents' => -30000,
        'company_reserved_delta_cents' => -30000,
    ]);

    $this->assertDatabaseHas('transactions', [
        'reference' => 'evt_cancel_before_auth_last',
        'type' => 'cancellation_release',
        'card_limit_delta_cents' => 50000,
        'company_balance_delta_cents' => 0,
        'company_reserved_delta_cents' => -50000,
    ]);
});

it('nao processa a mesma cancellation duas vezes', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_duplicate_cancel',
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

    $cancellationPayload = [
        'id' => 'evt_duplicate_cancel',
        'type' => 'cancellation',
        'occurred_at' => '2026-10-08T01:10:00Z',
        'authorization_id' => 'aut_duplicate_cancel',
    ];

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        $cancellationPayload,
    )->assertOk();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        $cancellationPayload,
    )->assertOk();

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(1000000)
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
        ->toBe(200000);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'aut_duplicate_cancel')
        ->firstOrFail();

    expect(
        App\Models\Cancellation::query()
            ->where('purchase_id', $purchase->id)
            ->count()
    )->toBe(1);

    expect(
        App\Models\Transaction::query()
            ->where('purchase_id', $purchase->id)
            ->where('type', 'cancellation_release')
            ->count()
    )->toBe(1);
});

it('nao cria transaction financeira ao cancelar compra sem reserva restante', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_full_capture_cancel',
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

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'capture_full_before_cancel',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_full_capture_cancel',
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'cancel_after_full_capture',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:20:00Z',
            'authorization_id' => 'aut_full_capture_cancel',
        ],
    )->assertOk();

    $purchase = Purchase::query()
        ->where(
            'authorization_network_id',
            'aut_full_capture_cancel',
        )
        ->firstOrFail();

    expect($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->captured_amount_cents)
        ->toBe(80000)
        ->and($purchase->status)
        ->toBe('canceled');

    $company = Company::query()->firstOrFail();

    expect($company->balance_cents)
        ->toBe(920000)
        ->and($company->reserved_cents)
        ->toBe(0);

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'cancel_after_full_capture',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'cancel_after_full_capture',
    ]);
});

it('aceita cancellation de authorization recusada sem criar transaction', function (): void {
    signedCancellationRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'aut_declined_cancel',
            'card_token' => 'tok_bruno',
            'amount_cents' => 60000,
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
            'reason' => 'monthly_limit_exceeded',
        ]);

    signedCancellationRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'cancel_declined',
            'type' => 'cancellation',
            'occurred_at' => '2026-10-08T01:10:00Z',
            'authorization_id' => 'aut_declined_cancel',
        ],
    )->assertOk();

    $purchase = Purchase::query()
        ->where(
            'authorization_network_id',
            'aut_declined_cancel',
        )
        ->firstOrFail();

    expect($purchase->reserved_amount_cents)
        ->toBe(0)
        ->and($purchase->has_cancellation)
        ->toBeTrue();

    $this->assertDatabaseHas('cancellations', [
        'network_id' => 'cancel_declined',
        'purchase_id' => $purchase->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'reference' => 'cancel_declined',
    ]);
});
