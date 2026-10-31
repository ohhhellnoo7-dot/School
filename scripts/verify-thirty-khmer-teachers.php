<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,User};
use App\Services\TeacherManagementService;
use Illuminate\Http\Request;

$teachers=User::where('role','teacher')->where('program','Khmer')->get();
$new=$teachers->where('teacher_record_source','generated_unverified');
if($teachers->count()!==30 || $new->count()!==20) throw new RuntimeException('Expected thirty Khmer records including twenty explicitly unverified additions.');
$rules=app(TeacherManagementService::class);
foreach($new as $teacher) {
    if(str_starts_with($teacher->name,'Sample ') || $rules->limit($teacher)!==2400 || $rules->qualifications($teacher)->count()>2 || $rules->periods($teacher)->where('unavailable',false)->count()!==10) throw new RuntimeException('Teacher name, workload or profile is incorrect.');
    $lessons=Schedule::where('teacher_id',$teacher->id)->get();
    if($lessons->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at))>2400) throw new RuntimeException('Weekly teaching limit exceeded.');
    foreach($lessons->groupBy('day') as $entries) if($entries->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at))>480) throw new RuntimeException('Daily teaching limit exceeded.');
}
$admin=User::where('role','administrator')->firstOrFail();
auth()->setUser($admin);
$request=Request::create('/dashboard','GET'); $request->setUserResolver(fn()=>$admin);
$dashboard=app(\App\Http\Controllers\DashboardController::class)->__invoke($request)->getData();
if($dashboard['programBreakdowns']['person-workspace']['Khmer']!==30 || $dashboard['teacherCount']!==50) throw new RuntimeException('Dashboard teacher counts are incorrect.');
$request=Request::create('/school/timetables','GET',['view'=>'teachers','program'=>'Khmer']); $request->setUserResolver(fn()=>$admin);
$view=app(\App\Http\Controllers\UnifiedTimetableController::class)->index($request);
if($view->getData()['teachers']->count()!==30) throw new RuntimeException('Timetable dropdown does not contain thirty Khmer records.');
$html=$view->with('errors',new \Illuminate\Support\ViewErrorBag())->render();
foreach($new as $teacher) if(!str_contains($html,e($teacher->name))) throw new RuntimeException('New teacher missing from dropdown.');
$confirmed=$new->filter(fn($teacher)=>$teacher->employee_confirmed_at!==null)->count();
if($confirmed===20 && str_contains($html,'(unverified)')) throw new RuntimeException('A manually confirmed employee still has an unverified label.');
echo json_encode(['verified'=>true,'khmer_records'=>30,'new_records'=>20,'dashboard_total_teachers'=>50,'teacher_dropdown_options'=>30,'manually_confirmed_new_records'=>$confirmed,'new_record_origin'=>'generated','independent_identity_check'=>false,'weekly_teaching_limit'=>40,'daily_teaching_limit'=>8],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
