<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Company;
use App\Models\CompanyDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * A gestora do painel já vem pronta. O restante do cadastro é seu.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'marina@acme.test'],
            ['name' => 'Marina', 'password' => 'password'],
        );

        $marina = User::query()->where('email', 'marina@acme.test')->firstOrFail();

        $company = Company::query()->firstOrCreate(
            ['name' => 'Acme'],
            [
                'balance_cents' => 1000000,
                'reserved_cents' => 0,
            ],
        );

        $deposit = CompanyDeposit::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'amount_cents' => 1000000,
            ],
            [
                'created_by_user_id' => $marina->id,
                'occurred_at' => now(),
            ],
        );

        Transaction::query()->firstOrCreate(
            ['reference' => 'deposit:'.$deposit->id],
            [
                'company_id' => $company->id,
                'purchase_id' => null,
                'card_id' => null,
                'month' => null,
                'type' => 'company_deposit',
                'card_limit_delta_cents' => 0,
                'company_balance_delta_cents' => 1000000,
                'company_reserved_delta_cents' => 0,
                'occurred_at' => $deposit->occurred_at,
            ],
        );

        $ana = User::query()->firstOrCreate(
            ['email' => 'ana@acme.test'],
            ['name' => 'Ana', 'password' => 'password'],
        );

        $bruno = User::query()->firstOrCreate(
            ['email' => 'bruno@acme.test'],
            ['name' => 'Bruno', 'password' => 'password'],
        );

        $carla = User::query()->firstOrCreate(
            ['email' => 'carla@acme.test'],
            ['name' => 'Carla', 'password' => 'password'],
        );

        $diego = User::query()->firstOrCreate(
            ['email' => 'diego@acme.test'],
            ['name' => 'Diego', 'password' => 'password'],
        );

        Card::query()->firstOrCreate(
            ['card_token' => 'tok_ana'],
            [
                'company_id' => $company->id,
                'user_id' => $ana->id,
                'monthly_limit_cents' => 200000,
                'purchase_limit_cents' => 80000,
                'status' => 'active',
                'blocked_mccs' => ['7995'],
            ],
        );

        Card::query()->firstOrCreate(
            ['card_token' => 'tok_bruno'],
            [
                'company_id' => $company->id,
                'user_id' => $bruno->id,
                'monthly_limit_cents' => 50000,
                'purchase_limit_cents' => null,
                'status' => 'active',
                'blocked_mccs' => [],
            ],
        );

        Card::query()->firstOrCreate(
            ['card_token' => 'tok_carla'],
            [
                'company_id' => $company->id,
                'user_id' => $carla->id,
                'monthly_limit_cents' => 100000,
                'purchase_limit_cents' => null,
                'status' => 'blocked',
                'blocked_mccs' => [],
            ],
        );

        Card::query()->firstOrCreate(
            ['card_token' => 'tok_diego'],
            [
                'company_id' => $company->id,
                'user_id' => $diego->id,
                'monthly_limit_cents' => 5000000,
                'purchase_limit_cents' => null,
                'status' => 'active',
                'blocked_mccs' => [],
            ],
        );
    }
}
