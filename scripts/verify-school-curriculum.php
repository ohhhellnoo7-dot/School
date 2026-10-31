<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use App\Support\KhmerLessonDuration;

$classes = SchoolClass::with('subjects')->get();
$rules = app(TeacherManagementService::class);
if (Subject::whereNull('required_weekly_minutes')->exists()) throw new RuntimeException('Missing curriculum hours remain.');
$lines = ['# School curriculum settings', '', 'Weekly subject hours are configured for every existing class/session. Incomplete classes use editable balanced school defaults based on their existing program catalog, configured session hours, and configured relaxation requirement. These are school settings, not an official national curriculum. A complete existing timetable supplied its own allocations and actual relaxation hours.', '', '## Weekly allocations', '', '| Class | Study hours | Relaxation hours | Session capacity |', '|---|---|---|---|'];
foreach ($classes as $class) {
    if ($class->subjects->isEmpty()) throw new RuntimeException('No subjects: '.$class->label);
    [$start,$end] = $class->sessionHours();
    $capacity = $rules::duration($start,$end) * count($rules::DAYS);
    $study = (int)$class->subjects->sum('required_weekly_minutes');
    if ($study + (int)$class->weekly_relaxation_minutes > $capacity) throw new RuntimeException('Curriculum exceeds session capacity: '.$class->label);
    foreach ($class->subjects as $subject) if ($subject->required_weekly_minutes % KhmerLessonDuration::minutes($subject) !== 0) throw new RuntimeException('Invalid lesson block allocation: '.$subject->id);
    $lines[] = '|'.$class->label.'|'.($study/60).'|'.($class->weekly_relaxation_minutes/60).'|'.($capacity/60).'|';
}
$lines = array_merge($lines,['', '## Teacher setup and capacity', '', 'English and Chinese qualifications were imported from existing assignments with user approval. The two-subject rule applies to Khmer teachers. Existing lessons were preserved. Working hours and staffing shortages below still require attention before all gaps can be filled.', '', '| Teacher | Program | Qualifications | Duty windows | Scheduled / limit (hours) |', '|---|---|---|---|---|']);
foreach (User::where('role','teacher')->where('status','active')->get() as $teacher) {
    $work = $rules->workload($teacher);
    $lines[] = '|'.$teacher->name.'|'.$teacher->program.'|'.$rules->qualifications($teacher)->join(', ').'|'.$rules->periods($teacher)->count().'|'.$work['weekly'].' / '.$work['limit'].'|';
}
$disk = \Illuminate\Support\Facades\Storage::disk('local');
$latest = collect($disk->files('timetable-drafts'))->filter(fn ($path) => str_contains($path,'/fill-study-periods-applied-'))->sort()->last();
if ($latest) {
    $repair = json_decode($disk->get($latest),true,512,JSON_THROW_ON_ERROR);
    $lines = array_merge($lines,['','## Remaining unscheduled periods','','Last scheduling pass: '.$repair['added_count'].' additional lessons; '.$repair['blocked_count'].' hourly periods could not be assigned under current constraints. These are genuine timetable shortages, separate from the resolved missing-hour configuration.','','| Class | Unfilled hourly periods | Reasons |','|---|---|---|']);
    foreach (collect($repair['blocked'])->groupBy('class_id') as $entries) $lines[] = '|'.$entries->first()['class'].'|'.$entries->count().'|'.implode('; ',$entries->pluck('reasons')->flatten()->unique()->all()).'|';
}
file_put_contents(__DIR__.'/../docs/school-curriculum-setup.md',implode(PHP_EOL,$lines).PHP_EOL);
echo 'PASS: '.$classes->count().' class sessions have subjects and complete weekly-hour settings within session capacity; lesson block durations are valid.'.PHP_EOL;
echo 'Report: docs/school-curriculum-setup.md'.PHP_EOL;
