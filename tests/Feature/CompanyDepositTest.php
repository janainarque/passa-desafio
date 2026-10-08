<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyDepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

it('registra deposito e atualiza o saldo da empresa', function (): void {
    $marina = User::query()
        ->where('email', 'marina@acme.test')
        ->firstOrFail();

    $company = Company::query()->firstOrFail();

    $service = app(CompanyDepositService::class);

    $deposit = $service->create(
        companyId: $company->id,
        amountCents: 50000,
        createdByUserId: $marina->id,
        occurredAt: '2026-10-08 12:00:00',
    );

    $company->refresh();

    expect($company->balance_cents)
        ->toBe(1050000)
        ->and($company->reserved_cents)
        ->toBe(0)
        ->and($deposit->amount_cents)
        ->toBe(50000);

    $this->assertDatabaseHas('company_deposits', [
        'company_id' => $company->id,
        'amount_cents' => 50000,
        'created_by_user_id' => $marina->id,
    ]);

    $this->assertDatabaseHas('transactions', [
        'company_id' => $company->id,
        'type' => 'company_deposit',
        'company_balance_delta_cents' => 50000,
        'company_reserved_delta_cents' => 0,
    ]);
});
