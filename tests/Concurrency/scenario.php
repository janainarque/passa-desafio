<?php

declare(strict_types=1);

use App\Models\Card;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/guard.php';
require __DIR__.'/cleanup.php';

$suffix = bin2hex(random_bytes(6));

$userId = null;
$companyId = null;
$cardId = null;

try {
    DB::transaction(function () use (
        $suffix,
        &$userId,
        &$companyId,
        &$cardId,
    ): void {
        $user = User::query()->create([
            'name' => 'Auditoria Concorrencia',
            'email' => "audit_{$suffix}@example.test",
            'password' => bin2hex(random_bytes(16)),
        ]);

        $company = Company::query()->create([
            'name' => "Auditoria Concorrencia {$suffix}",
            'balance_cents' => 1000000,
            'reserved_cents' => 0,
        ]);

        $card = Card::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'card_token' => "tok_audit_{$suffix}",
            'monthly_limit_cents' => 200000,
            'purchase_limit_cents' => 80000,
            'status' => 'active',
            'blocked_mccs' => [],
        ]);

        $userId = $user->id;
        $companyId = $company->id;
        $cardId = $card->id;
    });

    echo PHP_EOL;
    echo 'Registros confirmados no PostgreSQL!'.PHP_EOL;
    echo "Usuario: {$userId}".PHP_EOL;
    echo "Empresa: {$companyId}".PHP_EOL;
    echo "Cartao: {$cardId}".PHP_EOL;

    $confirmed = Company::query()->findOrFail($companyId);

    echo "Saldo inicial: {$confirmed->balance_cents} centavos".PHP_EOL;

    // Enviar uma autorizacao pela entrada real da API.
    config()->set('services.network.secret', 'test-network-secret');

    $authorizationId = "audit_auth_{$suffix}";

    $payload = [
        'id' => $authorizationId,
        'card_token' => "tok_audit_{$suffix}",
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

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->getTimestamp();

    $signature = 'sha256.'.hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'test-network-secret',
    );

    // O middleware espera o prefixo "sha256=".
    $signature = 'sha256='.mb_substr($signature, mb_strlen('sha256.'));

    $request = Illuminate\Http\Request::create(
        '/api/network/authorizations',
        'POST',
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

    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    echo PHP_EOL;
    echo "HTTP autorizacao: {$response->getStatusCode()}".PHP_EOL;
    echo "Resposta: {$response->getContent()}".PHP_EOL;

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException(
            'A autorizacao nao retornou HTTP 200.'
        );
    }

    $purchase = App\Models\Purchase::query()
        ->where('authorization_network_id', $authorizationId)
        ->firstOrFail();

    $companyAfterAuthorization = Company::query()
        ->findOrFail($companyId);

    echo "Compra criada: {$purchase->id}".PHP_EOL;
    echo "Reserva da empresa: {$companyAfterAuthorization->reserved_cents} centavos".PHP_EOL;

    if (
        $purchase->authorized_amount_cents !== 10000
        || $companyAfterAuthorization->reserved_cents !== 10000
    ) {
        throw new RuntimeException(
            'A reserva financeira nao corresponde ao esperado.'
        );
    }

    // Duas requisicoes identicas de captura.
    $capturePayload = [
        'id' => "audit_cap_{$suffix}",
        'type' => 'capture',
        'authorization_id' => $authorizationId,
        'sequence' => 1,
        'amount_cents' => 3000,
        'currency' => 'BRL',
        'final' => false,
        'occurred_at' => '2026-10-08T12:01:00Z',
    ];

    $encodedPayload = base64_encode(
        json_encode($capturePayload, JSON_THROW_ON_ERROR)
    );

    $command = [
        PHP_BINARY,
        __DIR__.'/capture-worker.php',
        $encodedPayload,
    ];

    $processA = new Symfony\Component\Process\Process($command);
    $processB = new Symfony\Component\Process\Process($command);

    $processA->setTimeout(30);
    $processB->setTimeout(30);

    echo PHP_EOL;
    echo 'Iniciando duas capturas concorrentes...'.PHP_EOL;

    // Inicia o processo que bloqueia a compra no PostgreSQL.
    $lockProcess = new Symfony\Component\Process\Process([
        PHP_BINARY,
        __DIR__.'/lock-worker.php',
        $authorizationId,
    ]);

    $lockProcess->setTimeout(15);

    $lockProcess->start();

    try {
        // Aguarda a confirmacao de que o bloqueio foi adquirido.
        $deadline = microtime(true) + 4;
        $lockAcquired = false;

        while (microtime(true) < $deadline) {
            if (str_contains($lockProcess->getOutput(), 'LOCK_ACQUIRED')) {
                $lockAcquired = true;
                break;
            }

            if (! $lockProcess->isRunning()) {
                break;
            }

            usleep(50000);
        }

        if (! $lockAcquired) {
            throw new RuntimeException(
                'Nao foi possivel confirmar o bloqueio: '
                .$lockProcess->getOutput()
                .$lockProcess->getErrorOutput()
            );
        }

        echo 'Bloqueio PostgreSQL confirmado!'.PHP_EOL;

        // Inicia as duas capturas enquanto a compra esta bloqueada.
        $startedAt = microtime(true);

        $processA->start();
        $processB->start();

        $processA->wait();
        $processB->wait();

        $elapsedSeconds = microtime(true) - $startedAt;

        echo PHP_EOL;
        echo 'Tempo total das capturas: '
            .number_format($elapsedSeconds, 3)
            .' segundos'.PHP_EOL;

    } finally {
        // Garante que o controlador termine antes da limpeza.
        if ($lockProcess->isRunning()) {
            $lockProcess->wait();
        }

        // Nao deixar capturas ativas durante a limpeza.
        if ($processA->isRunning()) {
            $processA->wait();
        }

        if ($processB->isRunning()) {
            $processB->wait();
        }
    }

    if (! $lockProcess->isSuccessful()) {
        throw new RuntimeException(
            'Falha no controlador do bloqueio: '
            .$lockProcess->getErrorOutput()
        );
    }

    echo 'Bloqueio liberado pelo controlador.'.PHP_EOL;

    foreach ([
        'A' => $processA,
        'B' => $processB,
    ] as $name => $process) {
        echo PHP_EOL;
        echo "Processo {$name}:".PHP_EOL;
        echo $process->getOutput().PHP_EOL;

        if (! $process->isSuccessful()) {
            echo $process->getErrorOutput().PHP_EOL;

            throw new RuntimeException(
                "Processo {$name} terminou com erro."
            );
        }

        if (! preg_match(
            '/CAPTURE_RESULT=(\{[^\r\n]*\})/',
            $process->getOutput(),
            $matches,
        )) {
            throw new RuntimeException(
                "Processo {$name} nao retornou resultado valido."
            );
        }

        $result = json_decode(
            $matches[1],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if ($result['status'] !== 200) {
            throw new RuntimeException(
                "Processo {$name} retornou HTTP {$result['status']}."
            );
        }
    }

    // Conferir o estado financeiro depois das duas requisicoes.
    $captureCount = App\Models\Capture::query()
        ->where('purchase_id', $purchase->id)
        ->count();

    $transactionCount = App\Models\Transaction::query()
        ->where('purchase_id', $purchase->id)
        ->where('type', 'capture_settlement')
        ->count();

    $updatedPurchase = $purchase->fresh();
    $updatedCompany = Company::query()->findOrFail($companyId);

    echo PHP_EOL;
    echo 'RESULTADO FINANCEIRO'.PHP_EOL;
    echo "Capturas: {$captureCount}".PHP_EOL;
    echo "Transacoes de captura: {$transactionCount}".PHP_EOL;
    echo "Valor capturado: {$updatedPurchase->captured_amount_cents}".PHP_EOL;
    echo "Saldo da empresa: {$updatedCompany->balance_cents}".PHP_EOL;
    echo "Reserva da empresa: {$updatedCompany->reserved_cents}".PHP_EOL;

    if (
        $captureCount !== 1
        || $transactionCount !== 1
        || (int) $updatedPurchase->captured_amount_cents !== 3000
        || (int) $updatedCompany->balance_cents !== 997000
        || (int) $updatedCompany->reserved_cents !== 7000
    ) {
        throw new RuntimeException(
            'Falha: resultado financeiro inconsistente.'
        );
    }

    echo PHP_EOL;
    echo 'TESTE CONCORRENTE APROVADO!'.PHP_EOL;

} finally {
    if (
        $userId !== null
        && $companyId !== null
        && $cardId !== null
    ) {
        cleanupConcurrencyFixture(
            $userId,
            $companyId,
            $cardId,
            $suffix,
        );

        echo PHP_EOL;
        echo 'Limpeza concluida com sucesso!'.PHP_EOL;
    }

    if (
        User::query()->whereKey($userId)->exists()
        || Company::query()->whereKey($companyId)->exists()
        || Card::query()->whereKey($cardId)->exists()
    ) {
        throw new RuntimeException(
            'Falha: ainda existem registros do cenario no banco.'
        );
    }

    echo 'Verificacao concluida: nenhum registro do cenario permaneceu.'.PHP_EOL;

    if (
        App\Models\Purchase::query()
            ->where('authorization_network_id', "audit_auth_{$suffix}")
            ->exists()
        || App\Models\Authorization::query()
            ->where('network_id', "audit_auth_{$suffix}")
            ->exists()
    ) {
        throw new RuntimeException(
            'Falha: compra ou autorizacao permaneceu no banco.'
        );
    }

    echo 'Verificacao financeira concluida: compra e autorizacao removidas.'.PHP_EOL;

}
