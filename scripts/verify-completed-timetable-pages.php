<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$admin=\App\Models\User::where('role','administrator')->firstOrFail();
auth()->setUser($admin);
$app->singleton(\App\Services\SchoolRoomAssignmentRepair::class);
$classes=\App\Models\SchoolClass::orderBy('id')->get();
$year=now(config('classrooms.timezone'))->year.'-'.(now(config('classrooms.timezone'))->year+1);
foreach($classes as $index=>$class) {
    $request=\Illuminate\Http\Request::create('/school/timetables/data','GET',['view'=>'students','class'=>$class->id,'academic_year'=>$year]);
    $request->setUserResolver(fn()=>$admin);
    $payload=app(\App\Http\Controllers\UnifiedTimetableController::class)->data($request)->getData(true);
    foreach(['Incomplete timetable','Weekly curriculum hours are not configured','Scheduled hours exceed','No lesson scheduled','CONFLICT DETECTED','Room assignment needs review.'] as $bad) if(str_contains($payload['html'],$bad)) throw new RuntimeException('Timetable page still has an issue for #'.$class->id.' '.$class->label.': '.$bad);
    if(($index+1)%20===0) echo 'Verified '.($index+1).' class timetable pages.'.PHP_EOL;
}
echo 'PASS: all '.$classes->count().' rendered class timetable pages have complete linked lessons and no curriculum, empty-period, room-link or overlap warnings.'.PHP_EOL;
