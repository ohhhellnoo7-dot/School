<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Symfony\Component\Process\Process;

$connection = config('database.connections.'.config('database.default'));
if (($connection['driver'] ?? null) !== 'pgsql') throw new RuntimeException('A PostgreSQL connection is required.');
$binary = $argv[1] ?? 'pg_dump';
$path = storage_path('app/qa/school-before-khmer-'.date('Ymd-His').'.dump');
\Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
$process = new Process([$binary, '--host='.$connection['host'], '--port='.$connection['port'], '--username='.$connection['username'], '--dbname='.$connection['database'], '--format=custom', '--no-password', '--file='.$path], null, ['PGPASSWORD' => (string) $connection['password']]);
$process->setTimeout(120);
$process->mustRun();
if (! is_file($path) || filesize($path) < 100) throw new RuntimeException('Backup file is missing or empty.');
echo 'PostgreSQL recovery backup created: '.$path.' ('.filesize($path).' bytes)'.PHP_EOL;
