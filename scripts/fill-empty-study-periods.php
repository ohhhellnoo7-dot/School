<?php

// Additive repair: shared lesson records, normal validation, backup and audit report.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassroomBooking, Schedule, SchoolClass, User};
use App\Services\{LessonManagementService, TeacherManagementService, TimetableBackup};
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

$apply = in_array('--apply', $argv, true);
$result = app(TeacherManagementService::class)->locked(function () use ($apply) {
    $rules = app(TeacherManagementService::class);
    $service = app(LessonManagementService::class);
    $year = '2026-2027';
    $classes = SchoolClass::with('subjects')->whereNull('shared_timetable_class_id')->get()->sortBy(fn ($c) => $c->program === 'Khmer' && $c->name === 'Grade 10' && $c->section === 'A' && $c->session === 'Morning' ? 0 : $c->id);
    $teachers = User::where('role', 'teacher')->where('status', 'active')->get();
    $limits = $teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $rules->limit($teacher)]);
    $qualifications = DB::table('teacher_qualifications')->get()->groupBy('teacher_id')->map(fn ($rows) => $rows->pluck('subject_name'));
    $assignments = DB::table('subjects')->whereNotNull('teacher_id')->get()->groupBy('teacher_id')->map(fn ($rows) => $rows->pluck('name')->unique());
    $bookings = ClassroomBooking::with('classSession')->get();
    $lessons = Schedule::with('subject')->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
    $added = []; $blocked = [];
    $backup = $apply ? app(TimetableBackup::class)->create() : null;
    DB::beginTransaction();
    try {
        foreach ($classes as $class) {
            [$start, $end] = $class->sessionHours();
            foreach (TeacherManagementService::DAYS as $day) {
                for ($minute = $rules::minute($start); $minute < $rules::minute($end); $minute += 60) {
                    $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
                    if ($lessons->contains(fn ($s) => $s->school_class_id === $class->id && $s->day === $day && $rules::minute($s->starts_at) <= $minute && $rules::minute($s->ends_at) > $minute)) continue;
                    $reservations = $bookings->filter(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->day_of_week === $day && $b->starts_minute <= $minute && $b->ends_minute > $minute);
                    $reasons = [];
                    if ($class->subjects->isEmpty()) $reasons[] = 'No subjects configured';
                    if ($reservations->isEmpty()) $reasons[] = 'No matching classroom reservation';
                    $saved = null;
                    $subjects = $class->subjects->sortBy(fn ($subject) => $lessons->where('school_class_id', $class->id)->filter(fn ($s) => $s->subject?->name === $subject->name)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)));
                    foreach ($subjects as $subject) {
                        $duration = KhmerLessonDuration::minutes($subject);
                        if ($subject->required_weekly_minutes !== null && $lessons->where('subject_id', $subject->id)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) + $duration > $subject->required_weekly_minutes) { $reasons[] = 'Weekly subject target already met'; continue; }
                        if (! in_array($duration, [60, 120], true) || $minute + $duration > $rules::minute($end)) continue;
                        $finish = sprintf('%02d:%02d', intdiv($minute + $duration, 60), ($minute + $duration) % 60);
                        $eligible = $teachers->filter(fn ($teacher) => $teacher->program === $class->program && ($qualifications->get($teacher->id, collect())->contains($subject->name)))
                            ->sortBy(fn ($teacher) => [$teacher->id === $subject->teacher_id ? 0 : 1, $lessons->where('teacher_id', $teacher->id)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at))]);
                        if ($eligible->isEmpty()) $reasons[] = 'No qualified '.$subject->name.' teacher';
                        foreach ($eligible as $teacher) {
                            $commitments = $lessons->where('teacher_id', $teacher->id);
                            if ($assignments->get($teacher->id, collect())->merge($commitments->pluck('subject.name'))->push($subject->name)->filter()->unique()->count() > $rules->subjectLimit($teacher)) { $reasons[] = 'Khmer two-subject teacher limit'; continue; }
                            if ($commitments->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) + $duration > $limits[$teacher->id]) { $reasons[] = 'Teacher workload limit'; continue; }
                            foreach ($reservations as $reservation) {
                                if ($minute + $duration > $reservation->ends_minute) continue;
                                if ($lessons->contains(fn ($s) => $s->day === $day && $rules::overlaps($time, $finish, $s->starts_at, $s->ends_at) && ($s->school_class_id === $class->id || $s->classroom_id === $reservation->classroom_id || $s->teacher_id === $teacher->id))) { $reasons[] = 'Teacher, class or room already occupied'; continue; }
                                try {
                                    $saved = $service->save(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'classroom_id' => $reservation->classroom_id, 'day' => $day, 'starts_at' => $time, 'ends_at' => $finish, 'academic_year' => $year]);
                                    $saved->load('subject');
                                    $lessons->push($saved);
                                    $added[] = ['id' => $saved->id, 'class_id' => $class->id, 'class' => $class->label, 'day' => $day, 'start' => $time, 'end' => $finish, 'subject' => $subject->name, 'teacher' => $teacher->name, 'classroom_id' => $reservation->classroom_id];
                                    break 3;
                                } catch (ValidationException $exception) { $reasons[] = $exception->getMessage(); }
                            }
                        }
                    }
                    if (! $saved) $blocked[] = ['class_id' => $class->id, 'class' => $class->label, 'day' => $day, 'start' => $time, 'reasons' => array_values(array_unique($reasons ?: ['No valid lesson fits this period']))];
                }
            }
            echo 'Checked '.$class->label.'; added '.count($added).'; unresolved '.count($blocked).PHP_EOL;
        }
        $report = ['applied' => $apply, 'academic_year' => $year, 'backup' => $backup, 'added_count' => count($added), 'blocked_count' => count($blocked), 'added' => $added, 'blocked' => $blocked];
        $path = 'timetable-drafts/fill-study-periods-'.($apply ? 'applied-' : 'preview-').now()->format('Ymd-His').'.json';
        if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Unable to save report.');
        if ($apply) DB::commit(); else DB::rollBack();
        return ['applied' => $apply, 'added' => count($added), 'unresolved' => count($blocked), 'report' => $path, 'backup' => $backup];
    } catch (Throwable $exception) {
        DB::rollBack();
        throw $exception;
    }
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
