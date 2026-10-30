<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (DB::getDriverName() !== 'pgsql') throw new RuntimeException('PostgreSQL is required.');
DB::beginTransaction();
try {
    DB::statement('CREATE TEMP TABLE schedules (LIKE public.schedules INCLUDING ALL) ON COMMIT DROP');
    $row = ['id' => 1, 'school_class_id' => 1, 'subject_id' => 1, 'teacher_id' => 1, 'entry_type' => 'academic', 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => 'Probe'];
    DB::table('schedules')->insert($row + ['cancelled_at' => now()]);
    DB::table('schedules')->insert(array_replace($row, ['id' => 2]));
    DB::statement('SAVEPOINT duplicate_probe');
    $rejected = false;
    try { DB::table('schedules')->insert(array_replace($row, ['id' => 3])); }
    catch (\Illuminate\Database\QueryException $e) { if ((string) $e->getCode() !== '23505') throw $e; $rejected = true; }
    DB::statement('ROLLBACK TO SAVEPOINT duplicate_probe');
    if (! $rejected) throw new RuntimeException('Duplicate active slots were accepted.');
    echo 'PASS: PostgreSQL preserves cancelled history, permits replacement, and rejects duplicate active class slots. No school rows or sequences changed.'.PHP_EOL;
} finally { DB::rollBack(); }
