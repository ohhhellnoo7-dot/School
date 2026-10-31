<?php

namespace App\Services;

use App\Models\{Schedule, SchoolClass, User};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Archive identified sample accounts while preserving their original lesson history. */
class DemoTeacherRemovalService
{
    public function removeExtras(): array
    {
        return $this->remove(false);
    }

    public function removeAllSamples(): array
    {
        return $this->remove(true);
    }

    private function remove(bool $includeOriginalSamples): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($includeOriginalSamples) {
            $rules=app(TeacherManagementService::class);
            $extra=User::where('role','teacher')->where(function($q) use ($includeOriginalSamples) {
                $q->where('profile_number','like','DEMO-%')->orWhere('email','like','demo-timetable-%');
                if($includeOriginalSamples) $q->orWhere('name','like','Sample %')->orWhere('name','like','Fictional %')->orWhere(fn($generated)=>$generated->where('teacher_record_source','generated_unverified')->whereNull('employee_confirmed_at'));
            })->get();
            if($extra->isEmpty()) return ['removed'=>0,'reassigned_lessons'=>0,'unfilled_lessons'=>0];
            $backup=app(TimetableBackup::class)->create();
            $ids=$extra->modelKeys();
            $affected=Schedule::with(['subject','schoolClass'])->whereIn('teacher_id',$ids)->orderBy('day')->orderBy('starts_at')->get();
            Schedule::whereIn('id',$affected->modelKeys())->update(['cancelled_at'=>now()]);
            foreach(['subjects','school_classes','class_groups','classroom_bookings'] as $table) DB::table($table)->whereIn('teacher_id',$ids)->update(['teacher_id'=>null,'updated_at'=>now()]);
            DB::table('users')->whereIn('id',$ids)->update(['archived_at'=>now(),'status'=>'inactive','updated_at'=>now()]);
            if(Schema::hasTable('sessions')) DB::table('sessions')->whereIn('user_id',$ids)->delete();
            $teachers=User::where('role','teacher')->where('status','active')->get();
            $active=Schedule::whereNotNull('teacher_id')->get();
            $used=$active->groupBy('teacher_id')->map(fn($rows)=>$rows->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at)))->all();
            $qualifications=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->qualifications($t)]);
            $limits=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->limit($t)]);
            $periods=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->periods($t)]);
            $free=$teachers->mapWithKeys(fn($t)=>[$t->id=>$rules->breaks($t)]);
            $reassigned=[]; $unfilled=[];
            foreach($affected as $old) {
                $duration=$rules::duration($old->starts_at,$old->ends_at);
                $data=['school_class_id'=>$old->school_class_id,'subject_id'=>$old->subject_id,'classroom_id'=>$old->classroom_id,'day'=>$old->day,'starts_at'=>substr($old->starts_at,0,5),'ends_at'=>substr($old->ends_at,0,5),'academic_year'=>$old->academic_year ?: now()->year.'-'.(now()->year+1)];
                $saved=null;
                foreach($teachers->where('program',$old->schoolClass?->program)->sortBy(fn($t)=>$used[$t->id]??0) as $teacher) {
                    if(!$old->subject || !$qualifications[$teacher->id]->contains($old->subject->name) || ($used[$teacher->id]??0)+$duration>$limits[$teacher->id]) continue;
                    if(!$periods[$teacher->id]->contains(fn($p)=>!$p->unavailable && $p->weekday===$old->day && $rules::minute($p->start_time)<=$rules::minute($old->starts_at) && $rules::minute($p->end_time)>=$rules::minute($old->ends_at))) continue;
                    if($free[$teacher->id]->contains(fn($b)=>$b->weekday===$old->day && $rules::overlaps($old->starts_at,$old->ends_at,$b->start_time,$b->end_time))) continue;
                    if($active->contains(fn($s)=>$s->teacher_id===$teacher->id && $s->day===$old->day && $rules::overlaps($s->starts_at,$s->ends_at,$old->starts_at,$old->ends_at))) continue;
                    try {
                        $saved=app(LessonManagementService::class)->save($data+['teacher_id'=>$teacher->id]);
                        $active->push($saved);
                        $used[$teacher->id]=($used[$teacher->id]??0)+$duration;
                        DB::table('subjects')->where('id',$old->subject_id)->whereNull('teacher_id')->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                        $aliasIds=SchoolClass::where('shared_timetable_class_id',$old->school_class_id)->pluck('id');
                        DB::table('subjects')->whereIn('school_class_id',$aliasIds)->where('name',$old->subject->name)->whereNull('teacher_id')->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                        $reassigned[]=['old_lesson_id'=>$old->id,'new_lesson_id'=>$saved->id,'teacher_id'=>$teacher->id];
                        break;
                    } catch(ValidationException $exception) { /* Keep the gap if the original staff cannot cover this slot. */ }
                }
                if(!$saved) $unfilled[]=['old_lesson_id'=>$old->id,'class'=>$old->schoolClass?->label,'subject'=>$old->subject?->name,'day'=>$old->day,'start'=>$data['starts_at'],'end'=>$data['ends_at']];
            }
            $report=['scope'=>$includeOriginalSamples?'all_samples':'extra_demos','removed'=>$extra->count(),'archived_teacher_ids'=>$ids,'archived_teachers'=>$extra->map->only(['id','name','program'])->all(),'backup'=>$backup,'reassigned_lessons'=>count($reassigned),'unfilled_lessons'=>count($unfilled),'reassigned'=>$reassigned,'unfilled'=>$unfilled,'remaining_teachers'=>User::where('role','teacher')->get()->groupBy('program')->map->count()->all()];
            $path='timetable-drafts/'.($includeOriginalSamples?'removed-all-sample-teachers-':'removed-extra-demo-teachers-').Str::uuid().'.json';
            if(!Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save demo removal report.');
            return $report+['report'=>$path];
        });
    }
}
