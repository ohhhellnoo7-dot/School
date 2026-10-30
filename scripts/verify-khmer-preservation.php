<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$baseline = json_decode(ltrim(file_get_contents(storage_path('app/qa/khmer-before.json')), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
foreach ($baseline['hashes'] as $table => $hash) {
    $query = DB::table($table)->orderBy('id');
    if ($table === 'schedules') $query->select($baseline['lesson_columns']);
    if (! hash_equals($hash, hash('sha256', $query->get()->toJson()))) throw new RuntimeException('School rows changed in '.$table);
    echo 'PASS: original '.$table.' rows preserved'.PHP_EOL;
}
