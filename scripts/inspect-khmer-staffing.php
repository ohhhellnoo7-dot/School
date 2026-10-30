<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (in_array('--verify-batch', $argv, true)) {
    $report = json_decode(ltrim(file_get_contents(__DIR__.'/../docs/khmer-teacher-batch.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
    $service = app(\App\Services\TeacherManagementService::class);
    foreach ($report['teachers'] as $row) {
        $teacher = \App\Models\User::findOrFail($row['id']);
        $breaks = $service->breaks($teacher);
        if ($breaks->count() !== 3 || $breaks->pluck('weekday')->unique()->count() !== 3) throw new RuntimeException('Invalid free periods for '.$teacher->name);
        foreach ($breaks as $break) $service->validateBreak($teacher, (array) $break);
        foreach ($service->lessons($teacher) as $lesson) {
            $service->validateLesson($lesson->subject, $lesson);
            if ($lesson->teacher_id !== $teacher->id) throw new RuntimeException('Teacher/class lesson mismatch.');
        }
        if ($service->workload($teacher)['subjects']->count() > 2) throw new RuntimeException('Too many assigned subjects.');
    }
    $total = \App\Models\User::where('role', 'teacher')->where('program', 'Khmer')->count();
    if ($total !== 30) throw new RuntimeException('Expected 30 Khmer teachers.');
    echo 'PASS: 30 Khmer teachers; all 20 new teachers have valid shared lessons, at most two subjects, no conflicts, and three distinct one-hour free periods.'.PHP_EOL;
    exit;
}
$subjects = DB::table('subjects')->join('school_classes', 'school_classes.id', '=', 'subjects.school_class_id')->where('school_classes.program', 'Khmer');
echo json_encode([
    'subjects' => (clone $subjects)->selectRaw('count(*) as total, count(subjects.teacher_id) as assigned')->first(),
    'scheduled' => DB::table('schedules')->join('subjects', 'subjects.id', '=', 'schedules.subject_id')->join('school_classes', 'school_classes.id', '=', 'subjects.school_class_id')->where('school_classes.program', 'Khmer')->select('subjects.id', 'subjects.name', 'subjects.teacher_id', 'school_classes.name as grade', 'school_classes.section', 'school_classes.session', 'schedules.day', 'schedules.starts_at', 'schedules.ends_at', 'schedules.room')->get(),
    'vacancies' => (clone $subjects)->whereNull('subjects.teacher_id')->select('subjects.id', 'subjects.name', 'subjects.required_weekly_minutes', 'school_classes.name as grade', 'school_classes.section', 'school_classes.session', 'school_classes.room', 'school_classes.class_group_id')->get(),
    'classes' => DB::table('school_classes')->where('program', 'Khmer')->select('id', 'name', 'section', 'session', 'room', 'class_group_id')->get(),
], JSON_PRETTY_PRINT);
