<?php

// Explicit operational tool: dry run by default; --apply commits one atomic batch.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, Subject, User};
use App\Services\TeacherManagementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$apply = in_array('--apply', $argv, true);
$service = app(TeacherManagementService::class);
$report = $service->locked(function () use ($service, $apply) {
    $existingCount = User::where('role', 'teacher')->where('program', 'Khmer')->count();
    if ($existingCount !== 10) throw new RuntimeException('Expected 10 Khmer teachers before this one-time batch; found '.$existingCount.'. No changes applied.');
    $originalLessons = Schedule::orderBy('id')->get()->map(fn ($s) => collect($s->getAttributes())->except(['teacher_id', 'updated_at'])->all())->all();
    $unchanged = [];
    foreach (['students', 'school_classes', 'class_groups', 'class_sessions'] as $table) $unchanged[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    DB::beginTransaction();
    try {
        $pool = Subject::with(['schoolClass', 'schoolClass.classGroup'])
            ->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer'))
            ->whereIn('id', Schedule::where('entry_type', 'academic')->select('subject_id'))
            ->orderBy('id')->get();
        $catalog = $pool->pluck('name')->unique()->values();
        if ($catalog->isEmpty()) throw new RuntimeException('No existing Khmer lessons can be assigned.');
        $names = ['Sok Dara', 'Chan Sophea', 'Chea Vanna', 'Heng Bopha', 'Lim Piseth', 'Seng Sreymom', 'Phan Rithy', 'Keo Sreyneang', 'Ouk Visal', 'Long Chenda', 'Sao Sophal', 'Kim Sreypov', 'Meas Vuthy', 'Yim Kanha', 'Nhem Panha', 'Ros Sreyka', 'Touch Samnang', 'Chum Malis', 'Prak Sovan', 'Kong Rachana'];
        $teachers = []; $assigned = []; $rejections = []; $changes = [];
        foreach ($names as $index => $name) {
            $email = sprintf('sample-khmer-teacher-%02d@sample.scholar.invalid', $index + 1);
            if (User::where('email', $email)->exists()) throw new RuntimeException('A batch identifier already exists. No existing account will be overwritten.');
            $teacher = new User(['name' => 'Sample '.$name, 'email' => $email, 'password' => Str::random(48), 'gender' => $index % 2 ? 'female' : 'male', 'program' => 'Khmer', 'status' => 'active', 'qualification' => 'Fictional sample teacher for timetable planning; verify before real school use']);
            $teacher->role = 'teacher';
            $teacher->save();
            $qualifications = [$catalog[$index % $catalog->count()], $catalog[($index + 1) % $catalog->count()]];
            $periods = [];
            foreach ($service::DAYS as $day) foreach (array_slice($service::SESSIONS, 0, 2) as [$start, $end]) $periods[] = ['weekday' => $day, 'start_time' => $start, 'end_time' => $end, 'unavailable' => false];
            $service->saveProfile($teacher, array_values(array_unique($qualifications)), 1200, $periods);
            $teachers[] = $teacher;
        }
        // Round-robin allocation prevents the first teachers consuming all existing lessons.
        for ($round = 0; $round < 8; $round++) foreach ($teachers as $teacher) {
            if ($service->workload($teacher)['weekly'] >= 12) continue;
            $choices = $pool->reject(fn ($s) => isset($assigned[$s->id]))
                ->filter(fn ($s) => $service->qualifications($teacher)->contains($s->name))
                ->sortBy(fn ($s) => Schedule::where('subject_id', $s->id)->count());
            foreach ($choices as $candidate) {
                $subject = $candidate->fresh();
                $previousTeacher = $subject->teacher_id;
                $subject->teacher_id = $teacher->id;
                try {
                    $service->validateSubject($subject);
                    $subject->save();
                    $assigned[$subject->id] = $teacher->id;
                    $changes[] = ['subject_id' => $subject->id, 'previous_teacher_id' => $previousTeacher, 'teacher_id' => $teacher->id];
                    break;
                } catch (ValidationException $e) { $rejections[$candidate->id] = $e->getMessage(); }
            }
        }
        $rows = [];
        foreach ($teachers as $teacher) {
            if ($service->lessons($teacher)->isEmpty()) throw new RuntimeException('No valid teaching assignment was available for '.$teacher->name.'. Entire batch rolled back. '.json_encode(array_values(array_unique($rejections))));
            $service->saveBreaks($teacher, $service->proposeBreaks($teacher));
            $lessons = $service->lessons($teacher);
            foreach ($lessons as $lesson) $service->validateLesson($lesson->subject, $lesson);
            $workload = $service->workload($teacher);
            $rows[] = ['id' => $teacher->id, 'name' => $teacher->name, 'subjects' => $workload['subjects']->all(), 'classes' => $workload['classes']->pluck('label')->all(), 'rooms' => $lessons->pluck('room')->unique()->values()->all(), 'teaching_hours' => $workload['weekly'], 'free_periods' => $service->breaks($teacher)->count()];
        }
        $afterLessons = Schedule::orderBy('id')->get()->map(fn ($s) => collect($s->getAttributes())->except(['teacher_id', 'updated_at'])->all())->all();
        if ($originalLessons !== $afterLessons) throw new RuntimeException('Lesson times or class details changed. Entire batch rolled back.');
        foreach ($unchanged as $table => $json) if ($json !== DB::table($table)->orderBy('id')->get()->toJson()) throw new RuntimeException('Unexpected change to '.$table);
        if (User::where('role', 'teacher')->where('program', 'Khmer')->count() !== 30) throw new RuntimeException('Unexpected final teacher count.');
        $report = ['committed' => $apply, 'khmer_teachers' => 30, 'added' => count($teachers), 'reassigned_subjects' => count($assigned), 'existing_lesson_times_preserved' => true, 'teachers' => $rows, 'assignment_changes' => $changes];
        if ($apply) DB::commit(); else DB::rollBack();
        return $report;
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
