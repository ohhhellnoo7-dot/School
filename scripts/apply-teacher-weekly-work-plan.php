<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,User};
use App\Services\{TeacherManagementService,TimetableBackup};
use Illuminate\Support\Facades\{DB,Storage};

$result=app(TeacherManagementService::class)->locked(function() {
    $rules=app(TeacherManagementService::class);
    $before=$rules->settings();
    $lessons=Schedule::all();
    foreach($lessons->whereNotNull('teacher_id')->groupBy('teacher_id') as $entries) if($entries->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at))>1200) throw new RuntimeException('A teacher already exceeds 20 classroom hours. Resolve those lessons before lowering the limit.');
    $backup=app(TimetableBackup::class)->create();
    DB::table('teacher_management_settings')->where('id',1)->update(['weekly_minutes'=>1200,'preparation_minutes'=>600,'marking_minutes'=>300,'other_duties_minutes'=>300]);
    $capped=DB::table('teacher_profiles')->where('weekly_minutes','>',1200)->update(['weekly_minutes'=>1200]);
    $report=['previous_settings'=>$before,'work_plan'=>$rules->weeklyWorkPlan(),'teacher_limits_capped'=>$capped,'teacher_accounts'=>User::where('role','teacher')->count(),'active_lessons_preserved'=>$lessons->count(),'backup'=>$backup];
    $path='timetable-drafts/teacher-weekly-work-plan-'.now()->format('Ymd-His').'.json';
    if(!Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Could not save work plan report.');
    return $report+['report'=>$path];
});
echo json_encode($result,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
