<?php

declare(strict_types=1);

use App\Models\Authorization;
use App\Models\Capture;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.network.secret', 'test-network-secret');
    $this->seed();
});

function signedIdempotencyAuditRequest(string $uri, array $payload)
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

function idempotencyAuditAuthorization(string $id, int $amount = 10000): array
{
    return [
        'id' => $id,
        'card_token' => 'tok_ana',
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

function idempotencyAuditCapture(string $id, string $authorizationId, int $sequence, int $amount): array
{
    return [
        'id' => $id,
        'type' => 'capture',
        'authorization_id' => $authorizationId,
        'sequence' => $sequence,
        'amount_cents' => $amount,
        'currency' => 'BRL',
        'final' => false,
        'occurred_at' => '2026-10-08T12:01:00Z',
    ];
}

it('aceita replay identico de autorizacao sem reservar novamente', function (): void {
    $payload = idempotencyAuditAuthorization('audit_idem_auth_same');

    signedIdempotencyAuditRequest('/api/network/authorizations', $payload)
        ->assertOk()->assertJson(['decision' => 'approved']);

    signedIdempotencyAuditRequest('/api/network/authorizations', $payload)
        ->assertOk()->assertJson(['decision' => 'approved']);

    expect(Authorization::query()->where('network_id', $payload['id'])->count())->toBe(1)
        ->and(Company::query()->firstOrFail()->reserved_cents)->toBe(10000);
});

it('rejeita mesmo id de autorizacao com valor diferente', function (): void {
    signedIdempotencyAuditRequest('/api/network/authorizations',
        idempotencyAuditAuthorization('audit_idem_auth_conflict', 10000)
    )->assertOk();

    signedIdempotencyAuditRequest('/api/network/authorizations',
        idempotencyAuditAuthorization('audit_idem_auth_conflict', 90000)
    )->assertStatus(409);

    expect(Authorization::query()->where('network_id', 'audit_idem_auth_conflict')->count())->toBe(1)
        ->and(Company::query()->firstOrFail()->reserved_cents)->toBe(10000);
});

it('rejeita mesmo id de captura com valor diferente', function (): void {
    signedIdempotencyAuditRequest('/api/network/authorizations',
        idempotencyAuditAuthorization('audit_idem_cap_auth')
    )->assertOk();

    signedIdempotencyAuditRequest('/api/network/events',
        idempotencyAuditCapture('audit_idem_cap', 'audit_idem_cap_auth', 1, 3000)
    )->assertOk();

    signedIdempotencyAuditRequest('/api/network/events',
        idempotencyAuditCapture('audit_idem_cap', 'audit_idem_cap_auth', 1, 7000)
    )->assertStatus(409);

    expect(Capture::query()->where('network_id', 'audit_idem_cap')->count())->toBe(1)
        ->and(Transaction::query()->where('reference', 'audit_idem_cap')->count())->toBe(1);
});

it('rejeita mesma sequencia de captura com id e valor diferentes', function (): void {
    signedIdempotencyAuditRequest('/api/network/authorizations',
        idempotencyAuditAuthorization('audit_idem_seq_auth')
    )->assertOk();

    signedIdempotencyAuditRequest('/api/network/events',
        idempotencyAuditCapture('audit_idem_seq_first', 'audit_idem_seq_auth', 1, 3000)
    )->assertOk();

    signedIdempotencyAuditRequest('/api/network/events',
        idempotencyAuditCapture('audit_idem_seq_second', 'audit_idem_seq_auth', 1, 7000)
    )->assertStatus(409);

    expect(Capture::query()->where('purchase_id',
        Capture::query()->where('network_id', 'audit_idem_seq_first')->firstOrFail()->purchase_id
    )->count())->toBe(1);
});

it('rejeita mesmo id de captura com autorizacao diferente', function (): void {
    signedIdempotencyAuditRequest(
        '/api/network/authorizations',
        idempotencyAuditAuthorization('audit_auth_A')
    )->assertOk();

    signedIdempotencyAuditRequest(
        '/api/network/authorizations',
        idempotencyAuditAuthorization('audit_auth_B')
    )->assertOk();

    signedIdempotencyAuditRequest(
        '/api/network/events',
        idempotencyAuditCapture('audit_cap_same', 'audit_auth_A', 1, 3000)
    )->assertOk();

    signedIdempotencyAuditRequest(
        '/api/network/events',
        idempotencyAuditCapture('audit_cap_same', 'audit_auth_B', 1, 3000)
    )->assertStatus(409);

    expect(
        Capture::query()->where('network_id', 'audit_cap_same')->count()
    )->toBe(1);
});

it('rejeita mesmo id de captura com indicador final diferente', function (): void {
    signedIdempotencyAuditRequest(
        '/api/network/authorizations',
        idempotencyAuditAuthorization('audit_auth_final')
    )->assertOk();

    $original = idempotencyAuditCapture(
        'audit_cap_final',
        'audit_auth_final',
        1,
        3000
    );

    signedIdempotencyAuditRequest(
        '/api/network/events',
        $original
    )->assertOk();

    $alterada = $original;
    $alterada['final'] = true;

    signedIdempotencyAuditRequest(
        '/api/network/events',
        $alterada
    )->assertStatus(409);

    expect(
        Capture::query()
            ->where('network_id', 'audit_cap_final')
            ->firstOrFail()
            ->final
    )->toBeFalse();
});
