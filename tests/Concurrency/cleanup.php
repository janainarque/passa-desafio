<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function cleanupConcurrencyFixture(
    int $userId,
    int $companyId,
    int $cardId,
    string $suffix,
): void {
    $connection = DB::connection();

    $database = $connection
        ->selectOne('SELECT current_database() AS name')
        ->name;

    if (
        ! app()->environment('testing')
        || $connection->getDriverName() !== 'pgsql'
        || $database !== 'passa_test'
    ) {
        throw new RuntimeException(
            'Limpeza bloqueada: ambiente ou banco incorreto.'
        );
    }

    if (! preg_match('/^[a-f0-9]{12}$/', $suffix)) {
        throw new RuntimeException(
            'Identificador de auditoria invalido.'
        );
    }

    DB::transaction(function () use (
        $userId,
        $companyId,
        $cardId,
        $suffix,
    ): void {
        $user = User::query()->findOrFail($userId);
        $company = Company::query()->findOrFail($companyId);
        $card = Card::query()->findOrFail($cardId);

        if (
            $user->email !== "audit_{$suffix}@example.test"
            || $company->name !== "Auditoria Concorrencia {$suffix}"
            || $card->card_token !== "tok_audit_{$suffix}"
            || (int) $card->company_id !== $companyId
            || (int) $card->user_id !== $userId
        ) {
            throw new RuntimeException(
                'Limpeza bloqueada: registros nao correspondem ao cenario.'
            );
        }

        // Nao excluir uma empresa ou usuario que tenham
        // outros cartoes fora do cenario.
        if (
            Card::query()->where('company_id', $companyId)->count() !== 1
            || Card::query()->where('user_id', $userId)->count() !== 1
        ) {
            throw new RuntimeException(
                'Limpeza bloqueada: existem outros cartoes vinculados.'
            );
        }

        // Nosso cenario nao cria depositos.
        if (
            DB::table('company_deposits')
                ->where('company_id', $companyId)
                ->exists()
        ) {
            throw new RuntimeException(
                'Limpeza bloqueada: empresa possui depositos.'
            );
        }

        $purchaseIds = DB::table('purchases')
            ->where('card_id', $cardId)
            ->pluck('id')
            ->all();

        // Excluir primeiro os registros dependentes.
        DB::table('transactions')
            ->whereIn('purchase_id', $purchaseIds)
            ->orWhere('card_id', $cardId)
            ->delete();

        DB::table('captures')
            ->whereIn('purchase_id', $purchaseIds)
            ->delete();

        DB::table('cancellations')
            ->whereIn('purchase_id', $purchaseIds)
            ->delete();

        DB::table('purchase_issues')
            ->whereIn('purchase_id', $purchaseIds)
            ->delete();

        DB::table('authorizations')
            ->whereIn('purchase_id', $purchaseIds)
            ->orWhere('card_id', $cardId)
            ->delete();

        DB::table('card_month_balances')
            ->where('card_id', $cardId)
            ->delete();

        DB::table('purchases')
            ->whereIn('id', $purchaseIds)
            ->delete();

        // Agora os registros principais podem ser removidos.
        $card->delete();
        $company->delete();
        $user->delete();
    });
}
