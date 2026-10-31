<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Subject, User};
use App\Services\{TeacherManagementService, TimetableBackup};

$report = app(TeacherManagementService::class)->locked(function () {
    $rules = app(TeacherManagementService::class);
    $backup = app(TimetableBackup::class)->create();
    $pairs = [
        'Prof. Margaretta Harvey I' => ['Khmer', 'History'],
        'Prof. Benedict Cummerata' => ['Physics', 'Mathematics'],
        'Berry Keebler' => ['English', 'Khmer'],
        'Isabel Berge' => ['English', 'Khmer'],
    ];
    $updated = [];
    foreach ($pairs as $name => $pair) {
        $teacher = User::where('role', 'teacher')->where('program', 'Khmer')->where('name', $name)->sole();
        $workload = $rules->workload($teacher);
        if ($workload['subjects']->diff($pair)->isNotEmpty()) throw new RuntimeException('Unexpected existing assignment for '.$name);
        $class = $workload['classes']->first();
        if (! $class || $class->program !== 'Khmer') throw new RuntimeException('Missing Khmer cohort for '.$name);
        foreach ($pair as $index => $subjectName) {
            Subject::firstOrCreate(['code' => 'KHM-PAIR-CATALOG-'.$teacher->id.'-'.($index + 1)],
                ['name' => $subjectName, 'school_class_id' => $class->id, 'required_weekly_minutes' => 0]);
        }
        $periods = $rules->periods($teacher)->map(fn ($p) => collect((array) $p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        $rules->saveProfile($teacher, $pair, $rules->limit($teacher), $periods);
        foreach ($pair as $index => $subjectName) {
            $assigned = Subject::where('teacher_id', $teacher->id)->where('name', $subjectName)->exists();
            if (! $assigned) {
                // A separate assignment preserves any other teacher's subject record.
                Subject::where('code', 'KHM-PAIR-CATALOG-'.$teacher->id.'-'.($index + 1))->firstOrFail()
                    ->update(['teacher_id' => $teacher->id]);
            }
        }
        $updated[] = ['teacher' => $name, 'subjects' => $rules->workload($teacher)['subjects']->all()];
    }
    $verified = 0;
    foreach (User::where('role', 'teacher')->where('program', 'Khmer')->get() as $teacher) {
        if ($rules->workload($teacher)['subjects']->count() !== 2) throw new RuntimeException('Teacher does not have exactly two subjects: '.$teacher->name);
        $verified++;
    }
    return ['updated' => $updated, 'khmer_teachers_verified' => $verified, 'backup' => $backup];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
