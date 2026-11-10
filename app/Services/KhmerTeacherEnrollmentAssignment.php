<?php
namespace App\Services;
use App\Models\{Subject,User};
use Illuminate\Support\Facades\DB;
class KhmerTeacherEnrollmentAssignment
{
    public function assign(User $teacher): array
    {
        if ($teacher->program !== 'Khmer' || $teacher->status !== 'active') return [];
        return app(TeacherManagementService::class)->locked(function () use ($teacher) {
            $rules=app(TeacherManagementService::class); $names=$rules->qualifications($teacher);
            $periods=$rules->periods($teacher)->where('unavailable',false);
            if ($periods->isEmpty()) return [];
            $committed=Subject::forTeaching()->where('teacher_id',$teacher->id)->sum('required_weekly_minutes');
            $budget=max(0,$rules->limit($teacher)-$committed); $assigned=[];
            $subjects=Subject::whereNotNull('curriculum_requirement_id')->whereNull('teacher_id')->whereIn('name',$names)->whereHas('schoolClass',fn($q)=>$q->where('program','Khmer'))->with('schoolClass')->orderBy('id')->get();
            foreach ($subjects as $subject) {
                if ($subject->required_weekly_minutes>$budget) continue;
                [$start,$end]=$subject->schoolClass->sessionHours();
                if (! $periods->contains(fn($p)=>$rules::overlaps($start,$end,$p->start_time,$p->end_time))) continue;
                DB::table('subjects')->where('id',$subject->id)->whereNull('teacher_id')->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                $budget-=$subject->required_weekly_minutes; $assigned[]=$subject->id;
            }
            // When the timetable is already complete, give a new enrollee a compatible
            // existing class subject without moving lesson times or emptying the donor's timetable.
            if (! $assigned && ! \App\Models\Schedule::where('teacher_id',$teacher->id)->exists()) {
                $candidates=Subject::whereNotNull('curriculum_requirement_id')->whereIn('name',$names)->whereNotNull('teacher_id')->where('teacher_id','!=',$teacher->id)->whereHas('schoolClass',fn($q)=>$q->where('program','Khmer'))->get();
                foreach ($candidates as $subject) {
                    $rows=\App\Models\Schedule::where('subject_id',$subject->id)->get();
                    $donorHours=\App\Models\Schedule::where('teacher_id',$subject->teacher_id)->get()->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at));
                    $hours=$rows->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at));
                    if (! $hours || $donorHours<=$hours) continue;
                    $subject->teacher_id=$teacher->id;
                    try {
                        $rules->validateSubject($subject);
                        foreach ($rows as $lesson) {
                            $proposed=clone $lesson; $proposed->teacher_id=$teacher->id;
                            $rules->validateLesson($subject,$proposed,false);
                        }
                    }
                    catch (\Illuminate\Validation\ValidationException $e) { continue; }
                    app(TimetableBackup::class)->create();
                    DB::table('subjects')->where('id',$subject->id)->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                    \App\Models\Schedule::where('subject_id',$subject->id)->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                    $assigned[]=$subject->id;
                    break;
                }
            }
            return $assigned;
        });
    }
}
