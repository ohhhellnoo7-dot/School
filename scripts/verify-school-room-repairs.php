<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{DB,Storage};

$disk = Storage::disk('local');
$path = collect($disk->files('room-repairs'))->filter(fn ($path)=>str_contains($path,'/school-room-repaired-'))->sort()->last();
if (! $path) throw new RuntimeException('No completed automatic room repair report exists.');
$report = json_decode($disk->get($path),true,512,JSON_THROW_ON_ERROR);
$backup = json_decode($disk->get($report['backup']),true,512,JSON_THROW_ON_ERROR);
$expected = collect($report['before']['verified'])->keyBy('lesson_id');
foreach ($backup['tables'] as $table=>$rows) {
    $key = $table === 'teacher_profiles' ? 'teacher_id':'id';
    $after = DB::table($table)->get()->keyBy($key);
    if ($after->count() !== count($rows)) throw new RuntimeException('Record count changed: '.$table);
    foreach ($rows as $row) {
        $current = $after->get($row[$key]);
        if (! $current) throw new RuntimeException('A school record was deleted.');
        foreach ($row as $column=>$value) {
            if ($table === 'schedules' && $expected->has($row['id']) && in_array($column,['classroom_id','room','updated_at'],true)) {
                if ($column === 'classroom_id' && $current->$column !== $expected[$row['id']]['proposed_id']) throw new RuntimeException('Unexpected physical classroom.');
                if ($column === 'room' && $current->$column !== $expected[$row['id']]['physical_room']) throw new RuntimeException('Physical room label does not match.');
                continue;
            }
            if ($current->$column !== $value) throw new RuntimeException('Unexpected change: '.$table.' #'.$row[$key].' '.$column);
        }
    }
    echo 'PASS: '.$table.' preserved except the approved unambiguous room-reference corrections.'.PHP_EOL;
}
if (DB::table('lesson_room_corrections')->whereIn('schedule_id',$expected->keys())->count() < $expected->count()) throw new RuntimeException('Room correction history is incomplete.');
echo 'PASS: '.$report['repaired'].' room links repaired on the original lesson IDs; subjects, teachers, cohorts, years, times and cancellation states are unchanged.'.PHP_EOL;
