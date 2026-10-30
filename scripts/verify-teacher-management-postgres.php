<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

if (DB::getDriverName() !== 'pgsql') throw new RuntimeException('PostgreSQL is required.');
$first = DB::connection();
config(['database.connections.teacher_lock_probe' => config('database.connections.'.config('database.default'))]);
$second = DB::connection('teacher_lock_probe');
try {
    $first->beginTransaction();
    $first->table('teacher_management_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
    $second->beginTransaction(); $second->statement("SET LOCAL lock_timeout = '150ms'");
    $blocked = false;
    try { $second->table('teacher_management_settings')->where('id', 1)->lockForUpdate()->firstOrFail(); }
    catch (\Illuminate\Database\QueryException $e) { if ((string)$e->getCode() !== '55P03') throw $e; $blocked = true; }
    finally { $second->rollBack(); }
    if (! $blocked) throw new RuntimeException('Concurrent writes were not serialized.');
    // TEMP clones shadow real tables for this connection; no school records or sequences are touched.
    foreach (['teacher_break_plans', 'teacher_breaks', 'teacher_qualifications'] as $table) $first->statement("CREATE TEMP TABLE {$table} (LIKE public.{$table} INCLUDING CONSTRAINTS INCLUDING INDEXES) ON COMMIT DROP");
    $first->unprepared('CREATE CONSTRAINT TRIGGER probe_plan AFTER INSERT OR UPDATE ON teacher_break_plans DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_teacher_break_plan(); CREATE CONSTRAINT TRIGGER probe_break AFTER INSERT OR UPDATE OR DELETE ON teacher_breaks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_teacher_break_plan();');
    $first->table('teacher_break_plans')->insert(['id' => 1, 'teacher_id' => 1]);
    foreach (['Monday', 'Tuesday', 'Wednesday'] as $index => $day) $first->table('teacher_breaks')->insert(['id' => $index + 1, 'teacher_break_plan_id' => 1, 'weekday' => $day, 'start_time' => '07:00', 'end_time' => '08:00', 'duration_minutes' => 60]);
    $first->statement('SET CONSTRAINTS ALL IMMEDIATE');
    $first->statement('SET CONSTRAINTS ALL DEFERRED');
    $reject = function (callable $callback) use ($first) {
        $first->statement('SAVEPOINT teacher_probe'); $rejected = false;
        try { $callback(); }
        catch (\Illuminate\Database\QueryException $e) { if (! in_array((string)$e->getCode(), ['23514', '23505'], true)) throw $e; $rejected = true; }
        finally { $first->statement('ROLLBACK TO SAVEPOINT teacher_probe'); }
        if (! $rejected) throw new RuntimeException('A database integrity constraint was bypassed.');
    };
    $reject(function () use ($first) { $first->table('teacher_break_plans')->insert(['id' => 2, 'teacher_id' => 2]); $first->statement('SET CONSTRAINTS ALL IMMEDIATE'); });
    $reject(fn () => $first->table('teacher_breaks')->where('id', 1)->update(['end_time' => '08:30']));
    $reject(fn () => $first->table('teacher_breaks')->where('id', 1)->update(['weekday' => 'Tuesday']));
    $reject(function () use ($first) { $first->table('teacher_breaks')->where('id', 1)->delete(); $first->statement('SET CONSTRAINTS ALL IMMEDIATE'); });
    $reject(fn () => $first->table('teacher_qualifications')->insert(['id' => 1, 'teacher_id' => 1, 'subject_name' => 'Probe', 'slot' => 3]));
    echo "PASS: concurrent writers serialize; completed plans require three distinct one-hour breaks; third subject slots are rejected. No school records changed.\n";
} finally {
    if ($second->transactionLevel()) $second->rollBack();
    if ($first->transactionLevel()) $first->rollBack();
    DB::purge('teacher_lock_probe');
}
