<?php

declare(strict_types=1);

use App\Models\Authorization;
use App\Models\Cancellation;
use App\Models\Capture;
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

function signedContractAuditRequest(string $uri, array $payload)
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

function contractAuditAuthorization(string $id): array
{
    return [
        'id' => $id,
        'card_token' => 'tok_ana',
        'amount_cents' => 10000,
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

function contractAuditCapture(string $id, string $authorizationId): array
{
    return [
        'id' => $id,
        'type' => 'capture',
        'authorization_id' => $authorizationId,
        'sequence' => 1,
        'amount_cents' => 3000,
        'currency' => 'BRL',
        'final' => false,
        'occurred_at' => '2026-10-08T12:01:00Z',
    ];
}

function contractAuditCancellation(string $id, string $authorizationId): array
{
    return [
        'id' => $id,
        'type' => 'cancellation',
        'authorization_id' => $authorizationId,
        'occurred_at' => '2026-10-08T12:02:00Z',
    ];
}

it('replay identico de authorization nao duplica a reserva', function (): void {
    $payload = contractAuditAuthorization('audit_contract_auth_repeat');

    signedContractAuditRequest('/api/network/authorizations', $payload)
        ->assertOk()->assertJson(['decision' => 'approved']);
    signedContractAuditRequest('/api/network/authorizations', $payload)
        ->assertOk()->assertJson(['decision' => 'approved']);

    expect(Authorization::query()->where('network_id', $payload['id'])->count())->toBe(1)
        ->and(Company::query()->firstOrFail()->reserved_cents)->toBe(10000);
});

it('replay identico de capture nao duplica o debito', function (): void {
    signedContractAuditRequest('/api/network/authorizations', contractAuditAuthorization('audit_contract_cap_same_auth'))->assertOk();
    $payload = contractAuditCapture('audit_contract_cap_same', 'audit_contract_cap_same_auth');

    signedContractAuditRequest('/api/network/events', $payload)->assertOk();
    signedContractAuditRequest('/api/network/events', $payload)->assertOk();

    expect(Capture::query()->where('network_id', $payload['id'])->count())->toBe(1)
        ->and(Company::query()->firstOrFail()->balance_cents)->toBe(997000)
        ->and(Transaction::query()->where('reference', $payload['id'])->count())->toBe(1);
});

it('reemissao de capture com novo id e mesmo conteudo nao duplica o debito', function (): void {
    signedContractAuditRequest('/api/network/authorizations', contractAuditAuthorization('audit_contract_cap_new_auth'))->assertOk();
    $original = contractAuditCapture('audit_contract_cap_original', 'audit_contract_cap_new_auth');
    $reemission = array_replace($original, ['id' => 'audit_contract_cap_reemitted']);

    signedContractAuditRequest('/api/network/events', $original)->assertOk();
    signedContractAuditRequest('/api/network/events', $reemission)->assertOk();

    $purchase = Purchase::query()->where('authorization_network_id', 'audit_contract_cap_new_auth')->firstOrFail();
    expect(Capture::query()->where('purchase_id', $purchase->id)->count())->toBe(1)
        ->and($purchase->fresh()->captured_amount_cents)->toBe(3000)
        ->and(Company::query()->firstOrFail()->balance_cents)->toBe(997000)
        ->and(Transaction::query()->where('purchase_id', $purchase->id)->where('type', 'capture_settlement')->count())->toBe(1);
});

it('reemissao de capture pendente com novo id nao cria duas capturas', function (): void {
    $original = contractAuditCapture('audit_contract_pending_original', 'audit_contract_pending_auth');
    $reemission = array_replace($original, ['id' => 'audit_contract_pending_reemitted']);

    signedContractAuditRequest('/api/network/events', $original)->assertOk()->assertJson(['pending' => true]);
    signedContractAuditRequest('/api/network/events', $reemission)->assertOk();
    signedContractAuditRequest('/api/network/authorizations', contractAuditAuthorization('audit_contract_pending_auth'))->assertOk();

    $purchase = Purchase::query()->where('authorization_network_id', 'audit_contract_pending_auth')->firstOrFail();
    expect(Capture::query()->where('purchase_id', $purchase->id)->count())->toBe(1)
        ->and($purchase->fresh()->captured_amount_cents)->toBe(3000)
        ->and(Company::query()->firstOrFail()->balance_cents)->toBe(997000);
});

it('reemissao de cancellation com novo id nao libera reserva duas vezes', function (): void {
    signedContractAuditRequest('/api/network/authorizations', contractAuditAuthorization('audit_contract_cancel_auth'))->assertOk();
    $original = contractAuditCancellation('audit_contract_cancel_original', 'audit_contract_cancel_auth');
    $reemission = array_replace($original, ['id' => 'audit_contract_cancel_reemitted']);

    signedContractAuditRequest('/api/network/events', $original)->assertOk();
    signedContractAuditRequest('/api/network/events', $reemission)->assertOk();

    $purchase = Purchase::query()->where('authorization_network_id', 'audit_contract_cancel_auth')->firstOrFail();
    expect(Cancellation::query()->where('purchase_id', $purchase->id)->count())->toBe(1)
        ->and($purchase->fresh()->reserved_amount_cents)->toBe(0)
        ->and(Company::query()->firstOrFail()->reserved_cents)->toBe(0)
        ->and(Transaction::query()->where('purchase_id', $purchase->id)->where('type', 'cancellation_release')->count())->toBe(1);
});
