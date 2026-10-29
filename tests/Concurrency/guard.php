<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';

$app->make(
    Kernel::class
)->bootstrap();

$connection = DB::connection();

$database = $connection
    ->selectOne('SELECT current_database() AS name')
    ->name;

if (
    ! app()->environment('testing')
    || $connection->getDriverName() !== 'pgsql'
    || $database !== 'passa_test'
) {
    fwrite(
        STDERR,
        "ERRO: ambiente ou banco de dados incorreto.\n"
    );

    exit(1);
}

echo 'Ambiente: '.app()->environment().PHP_EOL;
echo "Banco: {$database}".PHP_EOL;
echo 'Conexao segura para a auditoria.'.PHP_EOL;
