<?php

// Workflow: export -> python scripts/solve-subject-teacher-repair.py ->
// optionally solve again with --refine -> validate -> apply. Validation rolls
// back its simulation; apply creates a backup and preserves cancelled lessons.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassroomBooking, Schedule, SchoolClass, Subject, User};
use App\Services\{LessonManagementService, RotationTimetableGenerator, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\{DB, Storage};

$mode = $argv[1] ?? 'export';
$year = $argv[2] ?? now()->year.'-'.(now()->year + 1);
if (! in_array($mode, ['export', 'validate', 'apply'], true) || ! preg_match('/^\d{4}-\d{4}$/', $year)) throw new RuntimeException('Usage: php scripts/repair-subject-teachers.php export|validate|apply [academic-year]');
$disk = Storage::disk('local');
$rules = app(TeacherManagementService::class);
$report = $rules->locked(function () use ($mode, $year, $disk, $rules) {
    $classes = SchoolClass::with('subjects')->where('program', 'Khmer')->whereNull('shared_timetable_class_id')->get()->keyBy('id');
    $lessons = Schedule::whereIn('school_class_id', $classes->modelKeys())->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
    $outside = Schedule::whereNotIn('id', $lessons->modelKeys())->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
    $teachers = User::where('role', 'teacher')->where('status', 'active')->where('program', 'Khmer')->get()->keyBy('id');
    $bookings = ClassroomBooking::with(['classSession', 'classroom'])->get();
    $fingerprint = app(RotationTimetableGenerator::class)->fingerprint();
    if ($mode === 'export') {
        $pinnedClass = $classes->filter(fn ($c) => $c->name === 'Grade 10' && $c->section === 'E' && $c->session === 'Morning')->sole();
        $pinnedTeacher = $teachers->where('name', 'Sok Dara')->sole();
        $data = ['fingerprint' => $fingerprint, 'academic_year' => $year, 'pinned_class_id' => $pinnedClass->id, 'pinned_teacher_id' => $pinnedTeacher->id,
            'lessons' => $lessons->map->getAttributes()->values()->all(), 'outside' => $outside->map->getAttributes()->values()->all(),
            'classes' => $classes->map(function ($class) use ($lessons, $bookings) {
                if ($class->subjects->sum('required_weekly_minutes') !== 1200 || $lessons->where('school_class_id', $class->id)->sum(fn ($r) => TeacherManagementService::duration($r->starts_at, $r->ends_at)) !== 1200) throw new RuntimeException('Repair requires an existing complete approved 20-hour week: '.$class->label);
                return ['id' => $class->id, 'session' => $class->session,
                    'subjects' => $class->subjects->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'hours' => $s->required_weekly_minutes / 60, 'duration' => \App\Support\KhmerLessonDuration::minutes($s) / 60])->values()->all(),
                    'reservations' => $bookings->filter(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session)->map(fn ($b) => ['day' => $b->day_of_week, 'classroom_id' => $b->classroom_id, 'room' => $b->classroom->room_number, 'starts_at' => substr($b->starts_at, 0, 5), 'ends_at' => substr($b->ends_at, 0, 5)])->values()->all()];
            })->values()->all(),
            'teachers' => $teachers->map(function ($teacher) use ($rules, $outside) {
                $available = [];
                foreach ($rules::DAYS as $d => $day) foreach ([7, 8, 9, 10, 13, 14, 15, 16] as $h => $hour) {
                    $from = sprintf('%02d:00', $hour); $to = sprintf('%02d:00', $hour + 1);
                    if ($rules->onDuty($teacher, $day, $from, $to)
                        && ! $rules->breaks($teacher)->contains(fn ($b) => $b->weekday === $day && $rules::overlaps($from, $to, $b->start_time, $b->end_time))
                        && ! $outside->contains(fn ($r) => $r->teacher_id === $teacher->id && $r->day === $day && $rules::overlaps($from, $to, $r->starts_at, $r->ends_at))) $available[] = $d * 8 + $h;
                }
                return ['id' => $teacher->id, 'name' => $teacher->name, 'subjects' => $rules->qualifications($teacher)->all(), 'limit' => ($rules->limit($teacher) - $outside->where('teacher_id', $teacher->id)->sum(fn ($r) => $rules::duration($r->starts_at, $r->ends_at))) / 60, 'available' => $available];
            })->values()->all()];
        $disk->put('timetable-drafts/subject-teacher-repair-input.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        return ['status' => 'exported', 'cohorts' => $classes->count(), 'teachers' => $teachers->count()];
    }

    $input = json_decode($disk->get('timetable-drafts/subject-teacher-repair-input.json'), true, 512, JSON_THROW_ON_ERROR);
    $plan = json_decode($disk->get('timetable-drafts/subject-teacher-repair-solved.json'), true, 512, JSON_THROW_ON_ERROR);
    if (! in_array($plan['status'], ['FEASIBLE', 'OPTIMAL'], true) || empty($plan['rows']) || $plan['academic_year'] !== $year || $input['academic_year'] !== $year || ! hash_equals($fingerprint, $plan['fingerprint']) || ! hash_equals($fingerprint, $input['fingerprint'])) throw new RuntimeException('No current, complete repair plan. Export and solve again.');
    $unchangedOutside = DB::table('schedules')->whereNotIn('id', $lessons->modelKeys())->orderBy('id')->get()->toJson();
    $backup = $mode === 'apply' ? app(TimetableBackup::class)->create() : null;
    DB::beginTransaction();
    try {
        Schedule::whereIn('id', $lessons->modelKeys())->update(['cancelled_at' => now()]);
        $records = []; $owners = []; $minutes = []; $classMinutes = [];
        foreach ($plan['rows'] as $row) {
            $class = $classes->get($row['school_class_id']);
            $subject = $class?->subjects->firstWhere('id', $row['subject_id']);
            if (! $subject || ! $teachers->has($row['teacher_id']) || $row['academic_year'] !== $year || $row['entry_type'] !== 'academic') throw new RuntimeException('Invalid repair row.');
            if (isset($owners[$subject->id]) && $owners[$subject->id] !== $row['teacher_id']) throw new RuntimeException('A subject still has multiple teachers.');
            $owners[$subject->id] = $row['teacher_id'];
            if ($class->id === $input['pinned_class_id'] && $subject->name === 'Khmer' && $row['teacher_id'] !== $input['pinned_teacher_id']) throw new RuntimeException('10E Khmer must remain assigned to Sok Dara.');
            $duration = $rules::duration($row['starts_at'], $row['ends_at']);
            $minutes[$subject->id] = ($minutes[$subject->id] ?? 0) + $duration;
            $classMinutes[$class->id] = ($classMinutes[$class->id] ?? 0) + $duration;
            if (! $bookings->contains(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->classroom_id === $row['classroom_id'] && $b->day_of_week === $row['day'] && $rules::minute($b->starts_at) <= $rules::minute($row['starts_at']) && $rules::minute($b->ends_at) >= $rules::minute($row['ends_at']))) throw new RuntimeException('Lesson is outside its classroom reservation.');
            $records[] = $row + ['generated_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        }
        foreach ($classes as $class) {
            if (($classMinutes[$class->id] ?? 0) !== 1200) throw new RuntimeException('Incomplete class week.');
            foreach ($class->subjects as $subject) if (($minutes[$subject->id] ?? 0) !== (int) $subject->required_weekly_minutes) throw new RuntimeException('Approved subject hours changed.');
        }
        foreach (array_chunk($records, 100) as $chunk) DB::table('schedules')->insert($chunk);
        $new = Schedule::whereIn('school_class_id', $classes->modelKeys())->where('academic_year', $year)->get();
        foreach ($new as $lesson) app(LessonManagementService::class)->validate($lesson);
        foreach ($owners as $subjectId => $teacherId) DB::table('subjects')->where('id', $subjectId)->update(['teacher_id' => $teacherId, 'updated_at' => now()]);
        if (DB::table('schedules')->whereNotIn('id', $lessons->modelKeys())->whereNotIn('id', $new->modelKeys())->orderBy('id')->get()->toJson() !== $unchangedOutside) throw new RuntimeException('An unrelated timetable changed.');
        $result = ['status' => $mode === 'apply' ? 'applied' : 'validated', 'backup' => $backup, 'cohorts' => $classes->count(), 'subjects' => count($owners), 'lessons' => count($records), 'changed_lessons' => $plan['changed_lessons'], '10E' => $new->where('school_class_id', $input['pinned_class_id'])->groupBy('subject_id')->map(fn ($rows) => ['subject' => $rows->first()->subject->name, 'teacher' => $rows->first()->teacher->name])->values()->all()];
        if ($mode === 'apply') DB::commit(); else DB::rollBack();
        return $result;
    } catch (Throwable $error) {
        DB::rollBack();
        throw $error;
    }
});
if ($mode !== 'export') $disk->put('timetable-drafts/subject-teacher-repair-'.$mode.'.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
