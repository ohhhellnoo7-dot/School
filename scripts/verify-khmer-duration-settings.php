<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subject;
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\{DB, Storage};

$report = json_decode(ltrim(file_get_contents(storage_path('app/qa/khmer-duration-settings-applied.json')), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
$backup = json_decode(Storage::disk('local')->get($report['backup']), true, 512, JSON_THROW_ON_ERROR);
$changes = collect($report['changes'])->keyBy('subject_id');
foreach ($backup['tables'] as $table => $rows) {
    $key = $table === 'teacher_profiles' ? 'teacher_id' : 'id';
    $after = DB::table($table)->get()->keyBy($key);
    if ($after->count() !== count($rows)) throw new RuntimeException('Record count changed in '.$table);
    foreach ($rows as $row) {
        $actual = $after->get($row[$key]);
        if (! $actual) throw new RuntimeException('Missing record in '.$table);
        foreach ($row as $column => $value) {
            if ($table === 'subjects' && $changes->has($row['id']) && in_array($column, ['lesson_minutes','updated_at'],true)) {
                if ($column === 'lesson_minutes' && (int) $actual->$column !== $changes[$row['id']]['lesson_minutes']) throw new RuntimeException('Incorrect duration setting.');
                continue;
            }
            if ($actual->$column !== $value) throw new RuntimeException('Unexpected change: '.$table.' #'.$row[$key].' '.$column);
        }
    }
    echo 'PASS: '.$table.' preserved except explicitly authorized duration settings.'.PHP_EOL;
}
foreach (Subject::with('schoolClass')->whereHas('schoolClass',fn ($q)=>$q->where('program','Khmer'))->get() as $subject) if ((int) $subject->lesson_minutes !== KhmerLessonDuration::minutes($subject)) throw new RuntimeException('Khmer subject duration is incorrect: '.$subject->id);
echo 'PASS: all existing Khmer subject duration settings match the new rules; weekly hours and all lesson records are unchanged.'.PHP_EOL;
