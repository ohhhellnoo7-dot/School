<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Classroom, Schedule, SchoolClass, User};
use App\Services\{ClassroomAvailabilityService, LessonManagementService, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

$apply = in_array('--apply', $argv, true);
$report = app(TeacherManagementService::class)->locked(function () use ($apply) {
    $class = SchoolClass::where('program', 'Khmer')->where('name', 'Grade 10')->where('section', 'A')->where('session', 'Morning')->sole();
    $room = Classroom::where('room_number', '10A')->sole();
    $service = app(LessonManagementService::class);
    $plan = [];
    foreach ([['Mathematics', '07:00', '09:00'], ['History', '09:00', '10:00'], ['Geography', '10:00', '11:00']] as [$name, $start, $end]) {
        $subjects = $class->subjects()->where('name', $name)->get();
        $existing = Schedule::where('school_class_id', $class->id)->where('day', 'Monday')->where('starts_at', $start)->where('ends_at', $end)->where('academic_year', '2026-2027')->whereIn('subject_id', $subjects->modelKeys())->first();
        if ($existing) {
            $service->validate($existing);
            $plan[] = ['id' => $existing->id, 'subject' => $name, 'teacher' => $existing->teacher->name, 'start' => $start, 'end' => $end, 'existing' => true];
            continue;
        }
        $candidates = User::where('role', 'teacher')->where('program', 'Khmer')->where('status', 'active')->orderBy('id')->get()->sortBy(fn ($teacher) => $subjects->contains('teacher_id', $teacher->id) ? 0 : 1);
        $chosen = null;
        $errors = [];
        foreach ($subjects as $subject) {
            foreach ($candidates as $teacher) {
                $data = ['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'room' => $room->room_number, 'entry_type' => 'academic', 'day' => 'Monday', 'starts_at' => $start, 'ends_at' => $end, 'academic_year' => '2026-2027'];
                try {
                    $service->validate(new Schedule($data));
                    $chosen = $data;
                    break 2;
                } catch (ValidationException $exception) {
                    $errors[] = $teacher->name.': '.$exception->getMessage();
                }
            }
        }
        if (! $chosen) throw new RuntimeException('No eligible teacher for '.$name.': '.implode('; ', array_unique($errors)));
        $plan[] = ['data' => $chosen, 'subject' => $name, 'teacher' => User::findOrFail($chosen['teacher_id'])->name, 'start' => $start, 'end' => $end];
    }
    if (! $apply) return ['preview' => $plan];
    $backup = app(TimetableBackup::class)->create();
    foreach ($plan as &$entry) {
        if (isset($entry['data'])) $entry['id'] = $service->save($entry['data'])->id;
        unset($entry['data']);
    }
    unset($entry);
    $verification = [];
    foreach (['07:00', '09:00', '10:00'] as $time) {
        $snapshot = app(ClassroomAvailabilityService::class)->forDateAndTime('2026-11-02', $time)->firstWhere('classroom.id', $room->id);
        if ($snapshot['status'] !== 'busy' || $snapshot['currentLesson']?->school_class_id !== $class->id) throw new RuntimeException('Room status verification failed.');
        $verification[$time] = ['status' => $snapshot['status'], 'subject' => $snapshot['activity'], 'teacher' => $snapshot['currentLesson']->teacher->name];
    }
    $report = ['class_id' => $class->id, 'room_id' => $room->id, 'academic_year' => '2026-2027', 'day' => 'Monday', 'backup' => $backup, 'lessons' => $plan, 'verification' => $verification];
    $path = 'timetable-drafts/10a-monday-'.now()->format('Ymd-His').'.json';
    if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Report could not be saved.');
    return $report + ['report' => $path];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
