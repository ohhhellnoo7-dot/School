<?php
require __DIR__.'/../vendor/autoload.php'; $app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{CurriculumProfile,SchoolClass,Schedule,User};
use App\Services\{KhmerClassTimetableReport,KhmerCurriculumService};
use Illuminate\Support\Facades\{DB,Storage};
$profiles=CurriculumProfile::with('requirements')->where('program','Khmer')->get();
$profileRows=$profiles->map(fn($p)=>['grade'=>$p->grade,'stream'=>$p->stream?:'general','subjects'=>$p->requirements->count(),'weekly_hours'=>$p->requirements->sum('weekly_minutes')/60,'status'=>$p->status]);
$report=app(KhmerClassTimetableReport::class)->report();
$issues=collect($report['identities'])->flatMap(fn($i)=>array_merge($i['issues']??[],collect($i['cohorts'])->flatMap(fn($c)=>$c['issues'])->all()))->unique()->values()->all();
if($issues) throw new RuntimeException('Timetable validation failed: '.implode('; ',array_slice($issues,0,5)));
$baselinePath='timetable-backups/20261110-215845-5542eb22-fb45-4ed6-9387-162180f7b599.json';
$preserved=[];
if(Storage::disk('local')->exists($baselinePath)) {
    $baseline=json_decode(Storage::disk('local')->get($baselinePath),true,512,JSON_THROW_ON_ERROR)['tables'];
    foreach(['students','student_class_enrollments','class_attendances','class_groups','class_sessions'] as $table) {
        $before=collect($baseline[$table])->sortBy('id')->values()->toJson();
        $after=DB::table($table)->orderBy('id')->get()->toJson();
        if($before!==$after) throw new RuntimeException('Preservation check failed for '.$table);
        $preserved[]=$table;
    }
    foreach(['users','subjects','schedules'] as $table) {
        $ids=collect($baseline[$table])->pluck('id');
        if(DB::table($table)->whereIn('id',$ids)->count()!==$ids->count()) throw new RuntimeException('Original records missing in '.$table);
        $preserved[]=$table.' original IDs';
    }
}
$ids=SchoolClass::where('program','Khmer')->pluck('id');
$result=['profiles'=>$profileRows,'valid_class_identities'=>$report['valid_identities'],'class_sessions'=>$report['cohort_count'],'active_lessons'=>Schedule::whereIn('school_class_id',$ids)->count(),'teacher_records'=>User::where('role','teacher')->where('program','Khmer')->count(),'assigned_teacher_records'=>Schedule::whereIn('school_class_id',$ids)->distinct()->count('teacher_id'),'issues'=>$issues,'preserved'=>$preserved,'readiness'=>app(KhmerCurriculumService::class)->readiness()];
Storage::disk('local')->put('timetable-drafts/khmer-provisional-verification-'.now()->format('Ymd-His').'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(collect($result)->except('readiness')->all(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
