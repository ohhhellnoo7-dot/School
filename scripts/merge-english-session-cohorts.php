<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\SchoolClass;

$membershipSnapshot = fn () => DB::table('student_class_enrollments as enrollments')
    ->join('school_classes as classes', 'classes.id', '=', 'enrollments.school_class_id')
    ->select('enrollments.student_id', 'classes.program', 'classes.session', 'classes.class_group_id')
    ->distinct()->orderBy('enrollments.student_id')->orderBy('classes.program')->orderBy('classes.session')->orderBy('classes.class_group_id')->get()->toJson();
$primarySnapshot = fn () => DB::table('students')->join('school_classes as classes', 'classes.id', '=', 'students.school_class_id')
    ->select('students.id', 'classes.program', 'classes.session', 'classes.class_group_id')->orderBy('students.id')->get()->toJson();
$marksSnapshot = fn () => DB::table('class_attendances')->join('school_classes as classes', 'classes.id', '=', 'class_attendances.school_class_id')
    ->select('class_attendances.id', 'class_attendances.student_id', 'class_attendances.date', 'class_attendances.status', 'classes.program', 'classes.session')
    ->orderBy('class_attendances.id')->get()->toJson();
$backup = storage_path('app/private/room-repairs/english-one-class-per-session-'.now()->format('Ymd-His').'.json');
$result = DB::transaction(function () use ($membershipSnapshot, $primarySnapshot, $marksSnapshot, $backup) {
    $before = [$membershipSnapshot(), $primarySnapshot(), $marksSnapshot()];
    $result = app(App\Services\EnglishSessionCohortRepair::class)->run($backup);
    if ($before !== [$membershipSnapshot(), $primarySnapshot(), $marksSnapshot()]) throw new RuntimeException('Student sessions or attendance changed. Repair rolled back.');
    if (SchoolClass::where('program', 'English')->select('class_group_id', 'session')->groupBy('class_group_id', 'session')->havingRaw('COUNT(*) > 1')->exists()) throw new RuntimeException('English duplicate sessions remain. Repair rolled back.');
    return $result;
});
echo 'Merged '.count($result).' duplicate English cohorts.'.PHP_EOL;
echo 'Verified: student program/session memberships and attendance marks are unchanged.'.PHP_EOL;
echo 'Backup: '.$backup.PHP_EOL;

$service = app(App\Services\ClassroomAvailabilityService::class);
$checked = 0;
foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
    $entries = $service->forMoment('2026-11-10 08:00', ['detail_program' => 'English', 'detail_session' => $session]);
    foreach ($entries as $entry) {
        if (! preg_match('/^E\d{2}$/', $entry['classroom']->room_number)) continue;
        $ids = collect(data_get($entry, 'programDetails.English.class_ids', []));
        if ($ids->count() !== 1) throw new RuntimeException('Room '.$entry['classroom']->room_number.' has '.$ids->count().' classes for '.$session);
        $checked++;
    }
}
echo 'Verified '.$checked.' room/session attendance selections: one class each.'.PHP_EOL;
