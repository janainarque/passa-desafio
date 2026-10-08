<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyNetworkSignature;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set(
        'services.network.secret',
        'test-network-secret',
    );

    Route::post(
        '/api/network/test-signature',
        fn () => response()->json(['ok' => true]),
    )->middleware(VerifyNetworkSignature::class);
});

it('aceita assinatura valida da rede', function (): void {
    $timestamp = (string) time();

    $body = json_encode([
        'id' => 'msg_123',
    ], JSON_THROW_ON_ERROR);

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    $response = $this->call(
        'POST',
        '/api/network/test-signature',
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

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);
});

it('rejeita assinatura invalida', function (): void {
    $timestamp = (string) time();
    $body = '{"id":"msg_123"}';
    $response = $this
        ->withHeaders([
            'X-Network-Timestamp' => $timestamp,
            'X-Network-Signature' => 'sha256=assinatura-invalida',
            'Content-Type' => 'application/json',
        ])
        ->call(
            'POST',
            '/api/network/test-signature',
            [],
            [],
            [],
            [],
            $body,
        );

    $response->assertUnauthorized();
});

it('rejeita timestamp fora da janela de cinco minutos', function (): void {
    $timestamp = (string) (time() - 301);

    $body = '{"id":"msg_123"}';

    $signature = 'sha256='.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    $response = $this
        ->withHeaders([
            'X-Network-Timestamp' => $timestamp,
            'X-Network-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])
        ->call(
            'POST',
            '/api/network/test-signature',
            [],
            [],
            [],
            [],
            $body,
        );

    $response->assertUnauthorized();
});
