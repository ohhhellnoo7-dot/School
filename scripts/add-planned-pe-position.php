<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use App\Services\{TeacherManagementService, TimetableBackup};
use Illuminate\Support\Str;
$report = app(TeacherManagementService::class)->locked(function () {
    $backup = app(TimetableBackup::class)->create();
    $teacher = User::firstWhere('email', 'planned-khmer-physical-education-1@staff.scholar.invalid');
    if (! $teacher) {
        $teacher = new User;
        $teacher->forceFill(['name' => 'Planned Physical Education Teacher 1', 'email' => 'planned-khmer-physical-education-1@staff.scholar.invalid', 'password' => Str::random(64), 'role' => 'teacher', 'program' => 'Khmer', 'status' => 'active', 'teacher_record_source' => 'generated_unverified', 'qualification' => 'Planned staffing position for provisional curriculum. Replace with an actual verified PE teacher.'])->save();
        $rules = app(TeacherManagementService::class);
        $periods = [];
        foreach ($rules::DAYS as $day) foreach ([['07:00', '11:00'], ['13:00', '17:00']] as [$start, $end]) $periods[] = ['weekday' => $day, 'start_time' => $start, 'end_time' => $end, 'unavailable' => false];
        $rules->saveProfile($teacher, ['Physical Education'], 2400, $periods);
        if ($rules->requiredBreaks($teacher)) $rules->saveBreaks($teacher, $rules->proposeBreaks($teacher));
    }
    return ['backup' => $backup, 'planned_position_id' => $teacher->id, 'verified' => false];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
