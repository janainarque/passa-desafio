<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.network.secret', 'test-network-secret');
    $this->seed();
});

/**
 * Requests reais à API, assinadas como os testes existentes.
 * Este arquivo é uma AUDITORIA: os asserts expressam invariantes desejados,
 * não resultados que já foram comprovados por execução.
 */
function signedFinancialAuditRequest(string $uri, array $payload)
{
    $timestamp = (string) Date::now()->getTimestamp();
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-network-secret');

    return test()->call('POST', $uri, [], [], [], [
        'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
        'HTTP_X_NETWORK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

function financialAuditAuthorization(string $id, string $token = 'tok_ana', int $amount = 10000): array
{
    return [
        'id' => $id,
        'card_token' => $token,
        'amount_cents' => $amount,
        'currency' => 'BRL',
        'mcc' => '5411',
        'merchant' => [
            'name' => 'Loja Auditoria',
            'city' => 'Brasilia',
            'country' => 'BR',
        ],
        'occurred_at' => '2026-10-08T12:00:00Z',
    ];
}

function financialAuditCapture(string $id, string $authorizationId, int $sequence, int $amount, bool $final): array
{
    return [
        'id' => $id,
        'type' => 'capture',
        'occurred_at' => '2026-10-08T12:02:00Z',
        'authorization_id' => $authorizationId,
        'amount_cents' => $amount,
        'currency' => 'BRL',
        'sequence' => $sequence,
        'final' => $final,
    ];
}

it('nao debita nenhuma empresa quando a captura pendente pertence a cartao inexistente', function (): void {
    // O seeder ja criou Acme. A segunda empresa revela selecao arbitraria.
    Company::query()->create([
        'name' => 'Empresa B',
        'balance_cents' => 200000,
        'reserved_cents' => 0,
    ]);

    $before = Company::query()->pluck('balance_cents', 'id')->all();

    signedFinancialAuditRequest('/api/network/events',
        financialAuditCapture('audit_unknown_capture', 'audit_unknown_auth', 1, 10000, true)
    )->assertOk()->assertJson(['received' => true, 'pending' => true]);

    signedFinancialAuditRequest('/api/network/authorizations',
        financialAuditAuthorization('audit_unknown_auth', 'token_inexistente')
    )->assertOk()->assertJson(['decision' => 'declined', 'reason' => 'card_not_found']);

    // Invariante de seguranca: sem identificar a empresa, nao pode escolher uma ao acaso.
    $after = Company::query()->pluck('balance_cents', 'id')->all();
    expect($after)->toBe($before);
});

it('sinaliza captura que chegou depois de cancelamento mesmo antes da autorizacao', function (): void {
    signedFinancialAuditRequest('/api/network/events', [
        'id' => 'audit_cancel_before_auth',
        'type' => 'cancellation',
        'occurred_at' => '2026-10-08T12:01:00Z',
        'authorization_id' => 'audit_cancel_auth',
    ])->assertOk()->assertJson(['pending' => true]);

    signedFinancialAuditRequest('/api/network/events',
        financialAuditCapture('audit_capture_after_cancel', 'audit_cancel_auth', 1, 3000, false)
    )->assertOk()->assertJson(['pending' => true]);

    signedFinancialAuditRequest('/api/network/authorizations',
        financialAuditAuthorization('audit_cancel_auth')
    )->assertOk()->assertJson(['decision' => 'approved']);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'audit_cancel_auth')->firstOrFail();

    expect($purchase->status)->toBe('canceled');
    $this->assertDatabaseHas('purchase_issues', [
        'purchase_id' => $purchase->id,
        'code' => 'capture_after_cancellation',
    ]);
});

it('mantem settled ao reconciliar captura nao final apos captura final', function (): void {
    signedFinancialAuditRequest('/api/network/events',
        financialAuditCapture('audit_final_1', 'audit_final_auth', 1, 8000, true)
    )->assertOk()->assertJson(['pending' => true]);

    signedFinancialAuditRequest('/api/network/events',
        financialAuditCapture('audit_nonfinal_2', 'audit_final_auth', 2, 1000, false)
    )->assertOk()->assertJson(['pending' => true]);

    signedFinancialAuditRequest('/api/network/authorizations',
        financialAuditAuthorization('audit_final_auth')
    )->assertOk()->assertJson(['decision' => 'approved']);

    $purchase = Purchase::query()
        ->where('authorization_network_id', 'audit_final_auth')->firstOrFail();

    expect($purchase->has_final_capture)->toBeTrue()
        ->and($purchase->status)->toBe('settled')
        ->and($purchase->reserved_amount_cents)->toBe(0)
        ->and($purchase->captured_amount_cents)->toBe(9000);
});
