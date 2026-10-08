<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

it('exibe a tela de login', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Passa');
});

it('funcionario consegue fazer login', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    $this->post('/login', [
        'email' => $ana->email,
        'password' => 'password',
    ])
        ->assertRedirect('/my-card');

    $this->assertAuthenticatedAs($ana);
});

it('rejeita senha incorreta', function (): void {
    $this->post('/login', [
        'email' => 'ana@acme.test',
        'password' => 'senha-errada',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('usuario nao autenticado nao acessa my card', function (): void {
    $this->get('/my-card')
        ->assertRedirect('/login');
});
