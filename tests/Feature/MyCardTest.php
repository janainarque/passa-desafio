<?php

declare(strict_types=1);

use App\Models\User;
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

it('funcionario visualiza apenas seu proprio cartao', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    $this->actingAs($ana)
        ->get('/my-card')
        ->assertOk()
        ->assertSee('tok_ana')
        ->assertDontSee('tok_bruno')
        ->assertDontSee('tok_carla')
        ->assertDontSee('tok_diego');
});

it('cartao bloqueado mostra disponivel zero', function (): void {
    $carla = User::query()
        ->where('email', 'carla@acme.test')
        ->firstOrFail();

    $this->actingAs($carla)
        ->get('/my-card')
        ->assertOk()
        ->assertSee('R$ 0,00')
        ->assertSee('tok_carla');
});

it('usuario sem cartao recebe 403', function (): void {
    $marina = User::query()
        ->where('email', 'marina@acme.test')
        ->firstOrFail();

    $this->actingAs($marina)
        ->get('/my-card')
        ->assertForbidden();
});

it('mostra limite e disponivel iniciais da Ana', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    $this->actingAs($ana)
        ->get('/my-card')
        ->assertOk()
        ->assertSee('R$ 2.000,00')
        ->assertSee('tok_ana');
});

it('nao exibe compras de outro funcionario', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    $this->actingAs($ana)
        ->get('/my-card')
        ->assertOk()
        ->assertDontSee('tok_bruno')
        ->assertDontSee('tok_carla')
        ->assertDontSee('tok_diego');
});

it('atualiza limite e historico da Ana apos autorizacao e captura', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    signedMyCardRequest(
        'POST',
        '/api/network/authorizations',
        [
            'id' => 'my_card_aut_1',
            'card_token' => 'tok_ana',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Restaurante Teste',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-08T03:00:00Z',
        ],
    )
        ->assertOk()
        ->assertJson([
            'decision' => 'approved',
        ]);

    signedMyCardRequest(
        'POST',
        '/api/network/events',
        [
            'id' => 'my_card_capture_1',
            'type' => 'capture',
            'occurred_at' => '2026-10-08T03:01:00Z',
            'authorization_id' => 'my_card_aut_1',
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'sequence' => 1,
            'final' => true,
        ],
    )->assertOk();

    $this->actingAs($ana)
        ->get('/my-card')
        ->assertOk()
        ->assertSee('tok_ana')
        ->assertSee('R$ 1.900,00')
        ->assertSee('my_card_aut_1');
});

function signedMyCardRequest(
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
