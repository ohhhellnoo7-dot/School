<?php

// Run with: php scripts/apply-chinese-weekday-subjects.php [--apply]
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, SchoolClass, Subject};
use App\Services\{LessonManagementService, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true);
$plan = ['Monday' => 'Chinese Vocabulary', 'Tuesday' => 'Chinese Reading', 'Wednesday' => 'Chinese Writing', 'Thursday' => 'Chinese Listening', 'Friday' => 'Chinese Speaking'];
$result = app(TeacherManagementService::class)->locked(function () use ($apply, $plan) {
    $classes = SchoolClass::where('program', 'Chinese')->whereNull('shared_timetable_class_id')->orderBy('id')->get();
    if ($classes->count() !== 12) throw new RuntimeException('Expected 12 Chinese timetable owners.');
    $backup = $apply ? app(TimetableBackup::class)->create() : null;
    $count = 0;
    foreach ($classes as $class) {
        $year = Schedule::where('school_class_id', $class->id)->whereNotNull('academic_year')->max('academic_year');
        if (! $year) throw new RuntimeException('No existing academic year for '.$class->name);
        foreach ($plan as $day => $name) {
            foreach ([['17:30', '18:30'], ['18:30', '19:30']] as [$start, $end]) {
            $lessons = Schedule::where('school_class_id', $class->id)->where('academic_year', $year)->where('day', $day)->whereTime('starts_at', $start)->whereTime('ends_at', $end)->get();
            if ($lessons->count() !== 1) throw new RuntimeException('Expected one lesson for '.$class->name.' '.$day.' '.$start);
            $lesson = $lessons->first();
            // Preserve each period's assigned teacher, room, year, and time.
            $teacher = $lesson->teacher;
            if (! $teacher || $teacher->program !== 'Chinese') throw new RuntimeException('Missing Chinese teacher.');
            $qualification = DB::table('teacher_qualifications')->where('teacher_id', $teacher->id);
            if (! (clone $qualification)->where('subject_name', $name)->exists()) {
                DB::table('teacher_qualifications')->insert(['teacher_id' => $teacher->id, 'subject_name' => $name, 'slot' => ((clone $qualification)->max('slot') ?? 0) + 1]);
            }
            $cohorts = SchoolClass::whereKey($class->id)->orWhere('shared_timetable_class_id', $class->id)->get();
            foreach ($cohorts as $cohort) {
                $subject = Subject::where('school_class_id', $cohort->id)->where('name', $name)->first();
                if (! $subject) $subject = new Subject(['school_class_id' => $cohort->id, 'code' => 'ZH-WEEK-'.$cohort->id.'-'.array_search($day, array_keys($plan)), 'name' => $name]);
                $subject->fill(['name' => $name, 'teacher_id' => $teacher->id, 'lesson_minutes' => 60])->save();
                if ($cohort->id === $class->id) $lesson->subject_id = $subject->id;
            }
            app(LessonManagementService::class)->validate($lesson);
            $lesson->save();
            $count++;
            }
        }
        // Keep curriculum totals aligned with all remaining active weekly lessons.
        $minutes = Schedule::with('subject')->where('school_class_id', $class->id)->where('academic_year', $year)->get()
            ->groupBy('subject.name')->map(fn ($rows) => $rows->sum(fn ($s) => TeacherManagementService::duration($s->starts_at, $s->ends_at)));
        foreach (SchoolClass::whereKey($class->id)->orWhere('shared_timetable_class_id', $class->id)->get() as $cohort) {
            foreach ($cohort->subjects as $subject) $subject->update(['required_weekly_minutes' => $minutes[$subject->name] ?? 0]);
        }
    }
    foreach ($plan as $day => $name) {
        $verified = Schedule::whereIn('school_class_id', $classes->modelKeys())->where('day', $day)
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereTime('starts_at', '17:30')->whereTime('ends_at', '18:30'))
                ->orWhere(fn ($q) => $q->whereTime('starts_at', '18:30')->whereTime('ends_at', '19:30')))
            ->whereHas('subject', fn ($q) => $q->where('name', $name))->count();
        if ($verified !== 24) throw new RuntimeException('Schedule verification failed for '.$day);
    }
    if (! $apply) DB::rollBack();
    return ['applied' => $apply, 'grades' => $classes->pluck('name')->all(), 'lessons_updated' => $count, 'periods' => ['17:30–18:30', '18:30–19:30'], 'backup' => $backup];
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
