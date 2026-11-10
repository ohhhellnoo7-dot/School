<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{DB,Storage};
use App\Models\SchoolClass;
use App\Services\{KhmerCurriculumService,TeacherManagementService,TimetableBackup};

$result=app(TeacherManagementService::class)->locked(function () {
    $snapshot=fn()=>collect(['users','students','student_class_enrollments','class_attendances','attendances','schedules','grades'])->mapWithKeys(fn($table)=>[$table=>hash('sha256',DB::table($table)->orderBy('id')->get()->toJson())])->all();
    $before=$snapshot(); $backup=app(TimetableBackup::class)->create();
    $service=app(KhmerCurriculumService::class); $changes=$service->syncAll();
    foreach(SchoolClass::with('classGroup')->where('program','Khmer')->get() as $class) {
        $profile=$service->profileFor($class); $active=$class->subjects()->whereNotNull('curriculum_requirement_id')->get();
        if($active->count()!==$profile->requirements->count() || $active->sum('required_weekly_minutes')!==1200) throw new RuntimeException('Curriculum verification failed for '.$class->id);
    }
    if($before!==$snapshot()) throw new RuntimeException('Preservation check failed. Curriculum update rolled back.');
    return ['backup'=>$backup,'configured_class_sessions'=>count($changes),'preserved'=>array_keys($before),'readiness'=>$service->readiness()];
});
$path='timetable-drafts/provisional-khmer-curriculum-'.now()->format('Ymd-His').'.json';
Storage::disk('local')->put($path,json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
echo 'Report: '.$path.PHP_EOL;
