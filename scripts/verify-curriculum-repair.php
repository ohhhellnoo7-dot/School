<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Schedule, SchoolClass};
use App\Services\TeacherManagementService;
use Illuminate\Support\Facades\Storage;
$year = '2026-2027';
$lessons = Schedule::with(['subject', 'teacher', 'schoolClass', 'classroom'])->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
$issues = []; $counts = []; $slots = [];
foreach (SchoolClass::with('subjects')->whereNull('shared_timetable_class_id')->get() as $class) {
    $rows = $lessons->where('school_class_id', $class->id);
    if ($rows->isEmpty()) continue;
    $counts[$class->program] = ($counts[$class->program] ?? 0) + 1;
    foreach ($class->subjects as $subject) {
        $minutes = $rows->where('subject_id', $subject->id)->sum(fn ($s) => TeacherManagementService::duration($s->starts_at, $s->ends_at));
        if ($subject->required_weekly_minutes !== null && $minutes !== (int) $subject->required_weekly_minutes) $issues[] = ['class' => $class->label, 'subject' => $subject->name, 'required' => $subject->required_weekly_minutes, 'actual' => $minutes];
    }
}
foreach ($lessons as $lesson) {
    foreach (['teacher_id', 'school_class_id', 'classroom_id'] as $field) {
        if (! $lesson->$field) continue;
        for ($m = TeacherManagementService::minute($lesson->starts_at); $m < TeacherManagementService::minute($lesson->ends_at); $m += 30) {
            $slot = $field.'|'.$lesson->$field.'|'.$lesson->day.'|'.$m;
            if (isset($slots[$slot])) $issues[] = ['conflict' => $field, 'lessons' => [$slots[$slot], $lesson->id]];
            $slots[$slot] = $lesson->id;
        }
    }
}
$rules = app(TeacherManagementService::class);
foreach ($lessons->whereNotNull('teacher_id')->groupBy('teacher_id') as $rows) {
    $teacher = $rows->first()->teacher;
    if (! $teacher || $teacher->program !== 'Khmer') continue;
    $load = $rows->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at));
    if ($load > $rules->limit($teacher)) $issues[] = ['teacher' => $teacher->name, 'overload' => $load];
    if ($rows->pluck('subject.name')->filter()->unique()->count() > $rules->subjectLimit($teacher)) $issues[] = ['teacher' => $teacher->name, 'subject_limit' => true];
}
$report = ['academic_year' => $year, 'classes_checked_by_program' => $counts, 'lessons_checked' => $lessons->count(), 'issues' => $issues];
Storage::disk('local')->put('timetable-drafts/curriculum-repair-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($issues ? 1 : 0);
