<?php

declare(strict_types=1);

use App\Models\Authorization;
use App\Models\Card;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;

require __DIR__.'/guard.php';

function cleanupAuthorizationConcurrency(
    int $companyId,
    array $userIds,
    array $cardIds,
    string $suffix,
): void {
    if (
        ! preg_match('/^[a-f0-9]{12}$/', $suffix)
        || count($userIds) !== 2
        || count($cardIds) !== 2
    ) {
        throw new RuntimeException('Identificadores de limpeza invalidos.');
    }

    $connection = DB::connection();
    $database = $connection
        ->selectOne('SELECT current_database() AS name')
        ->name;

    if (
        ! app()->environment('testing')
        || $connection->getDriverName() !== 'pgsql'
        || $database !== 'passa_test'
    ) {
        throw new RuntimeException('Limpeza bloqueada: banco incorreto.');
    }

    DB::transaction(function () use (
        $companyId,
        $userIds,
        $cardIds,
        $suffix,
    ): void {
        $company = Company::query()->findOrFail($companyId);

        if (
            $company->name !== "Auditoria Autorizacoes {$suffix}"
            || Card::query()->where('company_id', $companyId)->count() !== 2
            || DB::table('company_deposits')
                ->where('company_id', $companyId)
                ->exists()
        ) {
            throw new RuntimeException('Empresa fora do cenario de limpeza.');
        }

        foreach ([1, 2] as $number) {
            $index = $number - 1;
            $user = User::query()->findOrFail($userIds[$index]);
            $card = Card::query()->findOrFail($cardIds[$index]);

            if (
                $user->email !== "audit_concurrent_{$suffix}_{$number}@example.test"
                || $card->card_token !== "tok_concurrent_{$suffix}_{$number}"
                || (int) $card->company_id !== $companyId
                || (int) $card->user_id !== (int) $user->id
                || Card::query()->where('user_id', $user->id)->count() !== 1
            ) {
                throw new RuntimeException('Registros fora do cenario de limpeza.');
            }
        }

        $purchaseIds = DB::table('purchases')
            ->whereIn('card_id', $cardIds)
            ->pluck('id')
            ->all();

        DB::table('transactions')
            ->whereIn('purchase_id', $purchaseIds)
            ->orWhereIn('card_id', $cardIds)
            ->delete();

        foreach (['captures', 'cancellations', 'purchase_issues'] as $table) {
            DB::table($table)
                ->whereIn('purchase_id', $purchaseIds)
                ->delete();
        }

        DB::table('authorizations')
            ->whereIn('purchase_id', $purchaseIds)
            ->orWhereIn('card_id', $cardIds)
            ->delete();

        DB::table('card_month_balances')
            ->whereIn('card_id', $cardIds)
            ->delete();

        DB::table('purchases')
            ->whereIn('id', $purchaseIds)
            ->delete();

        Card::query()->whereIn('id', $cardIds)->delete();
        $company->delete();
        User::query()->whereIn('id', $userIds)->delete();
    });
}

$suffix = bin2hex(random_bytes(6));
$companyId = null;
$userIds = [];
$cardIds = [];

try {
    $fixture = DB::transaction(function () use ($suffix): array {
        $company = Company::query()->create([
            'name' => "Auditoria Autorizacoes {$suffix}",
            'balance_cents' => 100000,
            'reserved_cents' => 0,
        ]);

        $userIds = [];
        $cardIds = [];

        foreach ([1, 2] as $number) {
            $user = User::query()->create([
                'name' => "Auditoria Autorizacao {$number}",
                'email' => "audit_concurrent_{$suffix}_{$number}@example.test",
                'password' => bin2hex(random_bytes(16)),
            ]);

            $card = Card::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'card_token' => "tok_concurrent_{$suffix}_{$number}",
                'monthly_limit_cents' => 100000,
                'purchase_limit_cents' => 100000,
                'status' => 'active',
                'blocked_mccs' => [],
            ]);

            $userIds[] = $user->id;
            $cardIds[] = $card->id;
        }

        return [
            'company_id' => $company->id,
            'user_ids' => $userIds,
            'card_ids' => $cardIds,
        ];
    });

    $companyId = $fixture['company_id'];
    $userIds = $fixture['user_ids'];
    $cardIds = $fixture['card_ids'];

    echo 'Empresa e dois cartoes preparados.'.PHP_EOL;

    $processes = [];

    foreach ([1, 2] as $number) {
        $payload = [
            'id' => "audit_concurrent_auth_{$suffix}_{$number}",
            'card_token' => "tok_concurrent_{$suffix}_{$number}",
            'amount_cents' => 80000,
            'currency' => 'BRL',
            'mcc' => '5812',
            'merchant' => [
                'name' => 'Loja Auditoria',
                'city' => 'Brasilia',
                'country' => 'BR',
            ],
            'occurred_at' => '2026-10-28T12:00:00Z',
        ];

        $encodedPayload = base64_encode(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $process = new Process([
            PHP_BINARY,
            __DIR__.'/authorization-worker.php',
            $encodedPayload,
        ]);

        $process->setTimeout(30);
        $processes[$number] = $process;
    }

    echo 'Duas autorizacoes preparadas para execucao concorrente.'.PHP_EOL;

    $lockProcess = new Process([
        PHP_BINARY,
        __DIR__.'/company-lock-worker.php',
        $suffix,
    ]);

    $lockProcess->setTimeout(20);
    $lockProcess->start();

    try {
        $deadline = microtime(true) + 5;
        $lockPid = null;

        while (microtime(true) < $deadline) {
            if (preg_match(
                '/LOCK_PID=(\d+)/',
                $lockProcess->getOutput(),
                $matches,
            )) {
                $lockPid = (int) $matches[1];
                break;
            }

            if (! $lockProcess->isRunning()) {
                break;
            }

            Sleep::usleep(50000);
        }

        if ($lockPid === null) {
            throw new RuntimeException(
                'Bloqueio nao confirmado: '
                .$lockProcess->getOutput()
                .$lockProcess->getErrorOutput()
            );
        }

        echo "Bloqueio adquirido pela conexao {$lockPid}.".PHP_EOL;

        foreach ($processes as $process) {
            $process->start();
        }

        $deadline = microtime(true) + 6;
        $waiting = 0;

        while (microtime(true) < $deadline) {
            $waiting = (int) DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM pg_stat_activity
                 WHERE datname = current_database()
                   AND pid <> pg_backend_pid()
                   AND state = ?
                   AND wait_event_type = ?
                   AND query ILIKE ?',
                ['active', 'Lock', '%from "companies"%for update%'],
            )->total;

            if ($waiting >= 2) {
                break;
            }

            if (! $lockProcess->isRunning()) {
                break;
            }

            Sleep::usleep(50000);
        }

        if ($waiting < 2) {
            $sessions = DB::select(
                'SELECT pid, state, wait_event_type, wait_event,
                        pg_blocking_pids(pid) AS blockers,
                        LEFT(query, 180) AS sql_query
                 FROM pg_stat_activity
                 WHERE datname = current_database()
                   AND pid <> pg_backend_pid()
                 ORDER BY pid'
            );

            echo PHP_EOL.'DIAGNOSTICO DAS CONEXOES:'.PHP_EOL;

            foreach ($sessions as $session) {
                echo json_encode($session, JSON_THROW_ON_ERROR).PHP_EOL;
            }

            foreach ($processes as $number => $process) {
                echo "Worker {$number} ativo: "
                    .($process->isRunning() ? 'sim' : 'nao')
                    .PHP_EOL;
            }

            throw new RuntimeException(
                "Concorrencia nao comprovada: {$waiting} conexoes aguardando."
                .PHP_EOL
                .implode(PHP_EOL, array_map(
                    static fn ($number, $process): string => "Worker {$number}: "
                        .$process->getOutput()
                        .$process->getErrorOutput(),
                    array_keys($processes),
                    array_values($processes),
                ))
            );
        }

        echo 'Duas conexoes aguardando o mesmo bloqueio!'.PHP_EOL;

        foreach ($processes as $process) {
            $process->wait();
        }

    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        if ($lockProcess->isRunning()) {
            $lockProcess->stop(1);
        }
    }

    if (! $lockProcess->isSuccessful()) {
        throw new RuntimeException(
            'Falha no worker de bloqueio: '
            .$lockProcess->getErrorOutput()
        );
    }

    $decisions = [];

    foreach ($processes as $number => $process) {
        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                "Worker {$number} falhou: "
                .$process->getOutput()
                .$process->getErrorOutput()
            );
        }

        if (! preg_match(
            '/AUTHORIZATION_RESULT=(\{[^\r\n]*\})/',
            $process->getOutput(),
            $matches,
        )) {
            throw new RuntimeException(
                "Worker {$number} nao retornou resultado valido."
            );
        }

        $result = json_decode(
            $matches[1],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (($result['status'] ?? null) !== 200) {
            throw new RuntimeException(
                "Worker {$number} nao retornou HTTP 200."
            );
        }

        $decisions[] = $result['body']['decision'] ?? null;

        echo "Autorizacao {$number}: "
            .json_encode($result['body'], JSON_THROW_ON_ERROR)
            .PHP_EOL;
    }

    sort($decisions);

    if ($decisions !== ['approved', 'declined']) {
        throw new RuntimeException(
            'As decisoes das duas autorizacoes estao incorretas.'
        );
    }

    $authorizations = Authorization::query()
        ->whereIn('card_id', $cardIds)
        ->get();

    $approvedCount = $authorizations
        ->where('decision', 'approved')
        ->count();

    $declinedCount = $authorizations
        ->where('decision', 'declined')
        ->where('reason', 'insufficient_funds')
        ->count();

    $holdCount = Transaction::query()
        ->where('company_id', $companyId)
        ->where('type', 'authorization_hold')
        ->count();

    $holdAmount = (int) Transaction::query()
        ->where('company_id', $companyId)
        ->where('type', 'authorization_hold')
        ->sum('company_reserved_delta_cents');

    $company = Company::query()->findOrFail($companyId);

    $balance = (int) $company->balance_cents;
    $reserved = (int) $company->reserved_cents;
    $available = $balance - $reserved;

    echo PHP_EOL.'RESULTADO FINANCEIRO'.PHP_EOL;
    echo "Autorizacoes registradas: {$authorizations->count()}".PHP_EOL;
    echo "Aprovadas: {$approvedCount}".PHP_EOL;
    echo "Recusadas por saldo: {$declinedCount}".PHP_EOL;
    echo "Transacoes de reserva: {$holdCount}".PHP_EOL;
    echo "Total das reservas: {$holdAmount}".PHP_EOL;
    echo "Saldo: {$balance}".PHP_EOL;
    echo "Reservado: {$reserved}".PHP_EOL;
    echo "Disponivel: {$available}".PHP_EOL;

    if (
        $authorizations->count() !== 2
        || $approvedCount !== 1
        || $declinedCount !== 1
        || $holdCount !== 1
        || $holdAmount !== 80000
        || $balance !== 100000
        || $reserved !== 80000
        || $available !== 20000
    ) {
        throw new RuntimeException(
            'Falha: inconsistencia financeira nas autorizacoes.'
        );
    }

    echo PHP_EOL.'TESTE DE AUTORIZACOES CONCORRENTES APROVADO!'.PHP_EOL;

} finally {
    if (
        $companyId !== null
        && count($userIds) === 2
        && count($cardIds) === 2
    ) {
        cleanupAuthorizationConcurrency(
            $companyId,
            $userIds,
            $cardIds,
            $suffix,
        );

        echo 'Limpeza do cenario concluida.'.PHP_EOL;
    }
}
