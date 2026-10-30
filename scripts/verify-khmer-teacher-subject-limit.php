<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{DB, Storage};

$report = json_decode(ltrim(file_get_contents($argv[1] ?? storage_path('app/qa/subject-limit-applied.json')), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
$backup = json_decode(Storage::disk('local')->get($report['backup']), true, 512, JSON_THROW_ON_ERROR);
$subjectIds = array_merge([], ...array_column($report['changes'], 'subject_ids'));
$lessonIds = array_merge([], ...array_column($report['changes'], 'lesson_ids'));
foreach ($backup['tables'] as $table => $before) {
    $key = $table === 'teacher_profiles' ? 'teacher_id' : 'id';
    $after = DB::table($table)->get()->keyBy($key);
    if ($after->count() !== count($before)) throw new RuntimeException('Row count changed in '.$table);
    foreach ($before as $row) {
        $actual = $after->get($row[$key]);
        if (! $actual) throw new RuntimeException('Record was deleted from '.$table);
        foreach ($row as $column => $value) {
            if ($table === 'subjects' && in_array($row['id'], $subjectIds, true) && in_array($column, ['teacher_id', 'updated_at'], true)) {
                if ($column === 'teacher_id' && $actual->$column !== null) throw new RuntimeException('Excess subject still assigned.');
                continue;
            }
            if ($table === 'schedules' && in_array($row['id'], $lessonIds, true) && in_array($column, ['cancelled_at', 'updated_at'], true)) {
                if ($column === 'cancelled_at' && $actual->$column === null) throw new RuntimeException('Excess teaching lesson remains active.');
                continue;
            }
            if ($actual->$column !== $value) throw new RuntimeException('Unexpected change: '.$table.' #'.$row[$key].' '.$column);
        }
    }
    echo 'PASS: '.$table.' contains only the authorized assignment/cancellation changes.'.PHP_EOL;
}
$remaining = app(\App\Services\KhmerTeacherSubjectLimit::class)->preview();
if ($remaining['changes'] || $remaining['blocked']) throw new RuntimeException('Some Khmer teachers still exceed two subjects.');
echo 'PASS: no Khmer teacher has more than two distinct assigned or actively taught subjects.'.PHP_EOL;
