<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,SchoolClass,User};
use App\Services\{LessonManagementService,TeacherManagementService,TimetableBackup};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;

if(!in_array('--apply',$argv,true)) throw new RuntimeException('Use --apply to configure the original Khmer roster for up to eight classroom hours per weekday.');
$report=app(TeacherManagementService::class)->locked(function() {
    $rules=app(TeacherManagementService::class); $disk=Storage::disk('local');
    $source=collect($disk->files('timetable-drafts'))->filter(fn($p)=>str_contains($p,'/removed-extra-demo-teachers-'))->sortBy(fn($p)=>$disk->lastModified($p))->last();
    $gaps=$source ? json_decode($disk->get($source),true,512,JSON_THROW_ON_ERROR)['unfilled'] : [];
    $previousSettings=(array)$rules->settings();
    $backup=app(TimetableBackup::class)->create();
    DB::table('teacher_management_settings')->where('id',1)->update(['khmer_weekly_minutes'=>2400]);
    $teachers=User::where('role','teacher')->where('program','Khmer')->get();
    foreach($teachers as $teacher) {
        $periods=[];
        foreach($rules::DAYS as $day) foreach([['07:00','11:00'],['13:00','17:00']] as [$start,$end]) $periods[]=['weekday'=>$day,'start_time'=>$start,'end_time'=>$end,'unavailable'=>false];
        foreach($rules->periods($teacher)->where('unavailable',true) as $period) $periods[]=collect((array)$period)->only(['weekday','start_time','end_time','unavailable'])->all();
        $rules->saveProfile($teacher,$rules->qualifications($teacher)->all(),2400,$periods);
    }
    $active=Schedule::with('subject')->get();
    $used=$active->whereNotNull('teacher_id')->groupBy('teacher_id')->map(fn($rows)=>$rows->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at)))->all();
    $qualifications=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->qualifications($t)]);
    $breaks=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->breaks($t)]);
    $reassigned=[]; $unfilled=[];
    foreach($gaps as $gap) {
        $old=Schedule::withoutGlobalScope('active')->with(['subject','schoolClass'])->findOrFail($gap['old_lesson_id']);
        if($old->schoolClass?->program!=='Khmer') continue;
        if($active->contains(fn($s)=>$s->school_class_id===$old->school_class_id && $s->day===$old->day && $s->starts_at===$old->starts_at && $s->ends_at===$old->ends_at)) continue;
        $duration=$rules::duration($old->starts_at,$old->ends_at);
        if($old->subject && $old->subject->required_weekly_minutes!==null && $active->where('subject_id',$old->subject_id)->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at)) >= $old->subject->required_weekly_minutes) continue;
        $saved=null; $reasons=[];
        foreach($teachers->where('status','active')->sortBy(fn($t)=>$used[$t->id]??0) as $teacher) {
            if(!$old->subject || !$qualifications[$teacher->id]->contains($old->subject->name)) { $reasons[]='Subject qualification missing'; continue; }
            if(($used[$teacher->id]??0)+$duration>2400) { $reasons[]='40-hour teaching limit'; continue; }
            if($active->contains(fn($s)=>$s->teacher_id===$teacher->id && $s->day===$old->day && $rules::overlaps($s->starts_at,$s->ends_at,$old->starts_at,$old->ends_at))) { $reasons[]='Teacher already teaching'; continue; }
            if($breaks[$teacher->id]->contains(fn($b)=>$b->weekday===$old->day && $rules::overlaps($b->start_time,$b->end_time,$old->starts_at,$old->ends_at))) { $reasons[]='Designated teacher free period'; continue; }
            try {
                $data=['school_class_id'=>$old->school_class_id,'subject_id'=>$old->subject_id,'classroom_id'=>$old->classroom_id,'teacher_id'=>$teacher->id,'day'=>$old->day,'starts_at'=>substr($old->starts_at,0,5),'ends_at'=>substr($old->ends_at,0,5),'academic_year'=>$old->academic_year ?: now()->year.'-'.(now()->year+1)];
                $saved=app(LessonManagementService::class)->save($data);
                $active->push($saved); $used[$teacher->id]=($used[$teacher->id]??0)+$duration;
                DB::table('subjects')->where('id',$old->subject_id)->whereNull('teacher_id')->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                $aliasIds=SchoolClass::where('shared_timetable_class_id',$old->school_class_id)->pluck('id');
                DB::table('subjects')->whereIn('school_class_id',$aliasIds)->where('name',$old->subject->name)->whereNull('teacher_id')->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                $reassigned[]=['old_lesson_id'=>$old->id,'new_lesson_id'=>$saved->id,'teacher_id'=>$teacher->id];
                break;
            } catch(ValidationException $exception) { $reasons[]=$exception->getMessage(); }
        }
        if(!$saved) $unfilled[]=$gap+['reasons'=>array_values(array_unique($reasons))];
    }
    $report=['backup'=>$backup,'previous_settings'=>$previousSettings,'khmer_teacher_count'=>$teachers->count(),'khmer_teachers_with_confirmed_subjects'=>$qualifications->filter(fn($names)=>$names->isNotEmpty())->count(),'weekly_teaching_hours'=>40,'daily_teaching_hours'=>8,'school_sessions'=>['07:00–11:00','13:00–17:00'],'khmer_work_plan'=>$rules->weeklyWorkPlan('Khmer'),'reassigned_count'=>count($reassigned),'unfilled_khmer_lessons'=>count($unfilled),'reassigned'=>$reassigned,'unfilled'=>$unfilled];
    $path='timetable-drafts/khmer-eight-hour-teaching-'.now()->format('Ymd-His').'.json';
    if(!$disk->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Could not save teaching-policy report.');
    return $report+['report'=>$path];
});
unset($report['reassigned'],$report['unfilled']);
echo json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
