<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

it('permite que Marina acesse o painel administrativo', function (): void {
    $marina = User::query()
        ->where('email', 'marina@acme.test')
        ->firstOrFail();

    $this->actingAs($marina)
        ->get('/admin')
        ->assertSuccessful();
});

it('impede funcionario de acessar o painel administrativo', function (): void {
    $ana = User::query()
        ->where('email', 'ana@acme.test')
        ->firstOrFail();

    $this->actingAs($ana)
        ->get('/admin')
        ->assertForbidden();
});
