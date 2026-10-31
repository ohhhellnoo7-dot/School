<?php

namespace App\Services;

use App\Models\{ClassroomBooking, ClassSession, Schedule, SchoolClass, Subject, User};
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

/** Explicitly invoked demo rebuild using registered cohorts/rooms and labelled sample staff. */
class DemoSchoolTimetableRepair
{
    private array $pool = [];
    private array $createdTeachers = [];
    private array $committed = [];
    private array $programHours = [];

    public function apply(string $year, ?callable $progress = null): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($year, $progress) {
            $this->pool = $this->createdTeachers = $this->committed = $this->programHours = [];
            $backup = app(TimetableBackup::class)->create();
            $sessions = ClassSession::with('classGroup')->whereHas('classGroup',fn ($q)=>$q->where('status','active'))->orderBy('class_group_id')->orderBy('starts_at')->get();
            foreach ($sessions as $session) $this->programHours[$session->classGroup->program][$session->starts_at.'-'.$session->ends_at] = [substr($session->starts_at,0,5),substr($session->ends_at,0,5)];
            $bookings = ClassroomBooking::with('classroom')->get();
            $cohorts = []; $newClasses = []; $shared = [];
            foreach ($sessions as $session) {
                $matches = SchoolClass::where('class_group_id',$session->class_group_id)->where('session',$session->session)->orderBy('id')->get();
                $owner = $matches->first();
                $reservation = $bookings->first(fn ($b)=>$b->class_session_id === $session->id);
                if (! $reservation) throw new \RuntimeException('No room reservation for '.$session->classGroup->display_name.' '.$session->session);
                if (! $owner) {
                    $owner = SchoolClass::create(['name'=>$session->classGroup->name,'section'=>$session->classGroup->section ?: 'A','program'=>$session->classGroup->program,'session'=>$session->session,'class_group_id'=>$session->class_group_id,'room'=>$reservation->classroom->room_number,'capacity'=>$reservation->classroom->capacity,'weekly_relaxation_minutes'=>180]);
                    $newClasses[] = $owner->id;
                }
                if ($owner->shared_timetable_class_id) $owner->update(['shared_timetable_class_id'=>null]);
                app(CurriculumSetupService::class)->configure($owner);
                foreach ($matches->where('id','!=',$owner->id) as $alias) {
                    $alias->update(['shared_timetable_class_id'=>$owner->id,'weekly_relaxation_minutes'=>$owner->weekly_relaxation_minutes]);
                    $shared[$alias->id] = $owner->id;
                    // Both enrollment records follow their already shared normalized cohort curriculum.
                    DB::table('subjects')->where('school_class_id',$alias->id)->update(['required_weekly_minutes'=>0,'updated_at'=>now()]);
                    foreach ($owner->subjects()->get() as $subject) {
                        $copy = $alias->subjects()->where('name',$subject->name)->first();
                        if (! $copy) $copy = Subject::create(['school_class_id'=>$alias->id,'name'=>$subject->name,'code'=>'SHARED-'.$alias->id.'-'.$subject->id,'lesson_minutes'=>$subject->lesson_minutes]);
                        DB::table('subjects')->where('id',$copy->id)->update(['required_weekly_minutes'=>$subject->required_weekly_minutes,'lesson_minutes'=>$subject->lesson_minutes,'updated_at'=>now()]);
                    }
                }
                $studentCount = \App\Models\Student::whereIn('school_class_id',array_merge([$owner->id],$matches->modelKeys()))->where('status','active')->count();
                foreach (TeacherManagementService::DAYS as $day) {
                    $booking = $bookings->first(fn ($b)=>$b->class_session_id === $session->id && $b->day_of_week === $day);
                    if (! $booking || $booking->classroom->status !== 'active' || $booking->classroom->capacity < $studentCount || TeacherManagementService::minute($booking->starts_at)>TeacherManagementService::minute($session->starts_at) || TeacherManagementService::minute($booking->ends_at)<TeacherManagementService::minute($session->ends_at)) throw new \RuntimeException('Room reservation/capacity is invalid for '.$owner->label.' '.$day);
                }
                $cohorts[] = ['class'=>$owner,'session'=>$session];
            }
            // Preserve the user's completed 10A week; replace invalid legacy schedules with history intact.
            $preserveClass = SchoolClass::where('program','Khmer')->where('name','Grade 10')->where('section','A')->where('session','Morning')->first();
            $preserved = $preserveClass ? Schedule::where('school_class_id',$preserveClass->id)->where(fn ($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->get() : collect();
            $keepIds = [];
            if ($preserveClass && $preserved->sum(fn ($s)=>TeacherManagementService::duration($s->starts_at,$s->ends_at)) === 1200) {
                foreach ($preserved as $lesson) app(LessonManagementService::class)->validate($lesson);
                $keepIds = $preserved->modelKeys();
            }
            $cancelled = Schedule::where(fn ($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->whereNotIn('id',$keepIds)->update(['cancelled_at'=>now()]);
            foreach (Schedule::with('subject')->get() as $lesson) if ($lesson->teacher_id) $this->committed[$lesson->teacher_id][] = $lesson->getAttributes();
            $rules = app(TeacherManagementService::class);
            foreach (User::where('role','teacher')->where('status','active')->get() as $teacher) $this->pool[$teacher->id] = $this->teacherState($teacher);
            $saved = []; $preservedClasses = [];
            foreach ($cohorts as ['class'=>$class,'session'=>$session]) {
                if ($keepIds && $class->id === $preserveClass->id) { $preservedClasses[]=$class->id; continue; }
                $rows = $this->layout($class->fresh()->load('subjects'),$session,$bookings,$year);
                foreach (collect($rows)->where('entry_type','academic')->groupBy('subject_id') as $subjectId=>$tasks) {
                    $subject = Subject::findOrFail($subjectId);
                    $teacher = $this->chooseTeacher($class,$subject,$tasks->all());
                    DB::table('subjects')->where('id',$subject->id)->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                    foreach ($tasks as $task) {
                        $lesson = app(LessonManagementService::class)->save($task + ['teacher_id'=>$teacher->id]);
                        $this->committed[$teacher->id][] = $lesson->getAttributes();
                        $saved[]=$lesson->id;
                    }
                    foreach (SchoolClass::where('shared_timetable_class_id',$class->id)->get() as $alias) DB::table('subjects')->where('school_class_id',$alias->id)->where('name',$subject->name)->update(['teacher_id'=>$teacher->id,'updated_at'=>now()]);
                }
                foreach (collect($rows)->where('entry_type','relaxation') as $row) $saved[]=app(LessonManagementService::class)->saveRelaxation($row)->id;
                $progress && $progress($class->label,count($saved),count($this->createdTeachers));
            }
            $report=['academic_year'=>$year,'backup'=>$backup,'new_sample_teachers'=>$this->createdTeachers,'new_class_sessions'=>$newClasses,'shared_timetables'=>$shared,'preserved_classes'=>$preservedClasses,'preserved_lesson_ids'=>$keepIds,'cancelled_legacy_lessons'=>$cancelled,'saved_lesson_ids'=>$saved,'cohort_count'=>count($cohorts)];
            $path='timetable-drafts/demo-school-completion-'.Str::uuid().'.json';
            if (!Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Completion report could not be saved.');
            return $report+['report'=>$path];
        });
    }

    private function layout(SchoolClass $class, ClassSession $session, Collection $bookings, string $year): array
    {
        $rules=app(TeacherManagementService::class);
        $start=$rules::minute($session->starts_at); $end=$rules::minute($session->ends_at);
        $cells=[]; $rows=[];
        foreach ($rules::DAYS as $day) for ($m=$start;$m+60<=$end;$m+=60) $cells[$day][$m]=false;
        $row=function($day,$minute,$duration,$subject=null) use ($class,$bookings,$year,$session) {
            $booking=$bookings->first(fn($b)=>$b->class_session_id===$session->id && $b->day_of_week===$day);
            return ['school_class_id'=>$class->id,'subject_id'=>$subject?->id,'classroom_id'=>$booking->classroom_id,'day'=>$day,'starts_at'=>sprintf('%02d:%02d',intdiv($minute,60),$minute%60),'ends_at'=>sprintf('%02d:%02d',intdiv($minute+$duration,60),($minute+$duration)%60),'entry_type'=>$subject?'academic':'relaxation','academic_year'=>$year];
        };
        $relax=(int)$class->weekly_relaxation_minutes;
        if ($relax%60 || $relax>300) throw new \RuntimeException('Invalid relaxation requirement: '.$class->label);
        foreach (array_slice(['Monday','Wednesday','Friday','Tuesday','Thursday'],0,intdiv($relax,60)) as $day) { $cells[$day][$end-60]=true; $rows[]=$row($day,$end-60,60); }
        $tasks=[];
        foreach ($class->subjects as $subject) {
            $block=KhmerLessonDuration::minutes($subject);
            if ($subject->required_weekly_minutes===null || !in_array($block,[60,120],true) || $subject->required_weekly_minutes%$block) throw new \RuntimeException('Invalid curriculum block: '.$class->label.' '.$subject->name);
            for($m=0;$m<$subject->required_weekly_minutes;$m+=$block) $tasks[]=['subject'=>$subject,'duration'=>$block];
        }
        usort($tasks,fn($a,$b)=>$b['duration']<=>$a['duration']);
        foreach($tasks as $task) {
            $placed=false;
            foreach($rules::DAYS as $day) foreach($cells[$day] as $minute=>$busy) {
                if($busy || ($task['duration']===120 && (!array_key_exists($minute+60,$cells[$day]) || $cells[$day][$minute+60]))) continue;
                $cells[$day][$minute]=true;
                if($task['duration']===120) $cells[$day][$minute+60]=true;
                $rows[]=$row($day,$minute,$task['duration'],$task['subject']); $placed=true; break 2;
            }
            if(!$placed) throw new \RuntimeException('Curriculum does not fit '.$class->label.' with its configured relaxation periods.');
        }
        if(collect($cells)->contains(fn($day)=>in_array(false,$day,true))) throw new \RuntimeException('Curriculum leaves unconfigured study time: '.$class->label);
        return $rows;
    }

    private function teacherState(User $teacher): array
    {
        $rules=app(TeacherManagementService::class);
        return ['teacher'=>$teacher,'qualifications'=>$rules->qualifications($teacher)->all(),'subjects'=>Subject::where('teacher_id',$teacher->id)->pluck('name')->unique()->all(),'periods'=>$rules->periods($teacher)->map(fn($p)=>(array)$p)->all(),'breaks'=>$rules->breaks($teacher)->map(fn($b)=>(array)$b)->all(),'limit'=>$rules->limit($teacher)];
    }

    private function availableBreaks(array $state,array $tasks): ?array
    {
        $rules=app(TeacherManagementService::class);
        $busy=array_merge($this->committed[$state['teacher']->id]??[],$tasks);
        $options=[];
        foreach($state['periods'] as $period) {
            if($period['unavailable']) continue;
            for($m=$rules::minute($period['start_time']);$m+60<=$rules::minute($period['end_time']);$m+=60) {
                $start=sprintf('%02d:%02d',intdiv($m,60),$m%60); $end=sprintf('%02d:%02d',intdiv($m+60,60),($m+60)%60);
                if(collect($busy)->contains(fn($s)=>$s['day']===$period['weekday'] && $rules::overlaps($start,$end,$s['starts_at'],$s['ends_at']))) continue;
                if(collect($state['periods'])->contains(fn($p)=>$p['unavailable'] && $p['weekday']===$period['weekday'] && $rules::overlaps($start,$end,$p['start_time'],$p['end_time']))) continue;
                $options[$period['weekday']]=['weekday'=>$period['weekday'],'start_time'=>$start,'end_time'=>$end,'duration_minutes'=>60];
            }
        }
        return count($options)>=3 ? array_slice(array_values($options),0,3) : null;
    }

    private function fitsTeacher(array $state, SchoolClass $class, Subject $subject,array $tasks): bool
    {
        $rules=app(TeacherManagementService::class); $teacher=$state['teacher'];
        if($teacher->program!==$class->program || !in_array($subject->name,$state['qualifications'],true) || count(array_unique([...$state['subjects'],$subject->name]))>$rules->subjectLimit($teacher)) return false;
        $busy=$this->committed[$teacher->id]??[];
        if(collect(array_merge($busy,$tasks))->sum(fn($s)=>$rules::duration($s['starts_at'],$s['ends_at']))>$state['limit']) return false;
        foreach($tasks as $task) {
            $covers=collect($state['periods'])->contains(fn($p)=>!$p['unavailable'] && $p['weekday']===$task['day'] && $rules::minute($p['start_time'])<=$rules::minute($task['starts_at']) && $rules::minute($p['end_time'])>=$rules::minute($task['ends_at']));
            if(!$covers || collect($state['periods'])->contains(fn($p)=>$p['unavailable'] && $p['weekday']===$task['day'] && $rules::overlaps($task['starts_at'],$task['ends_at'],$p['start_time'],$p['end_time']))) return false;
            if(collect($busy)->contains(fn($s)=>$s['day']===$task['day'] && $rules::overlaps($task['starts_at'],$task['ends_at'],$s['starts_at'],$s['ends_at']))) return false;
            if(collect($state['breaks'])->contains(fn($b)=>$b['weekday']===$task['day'] && $rules::overlaps($task['starts_at'],$task['ends_at'],$b['start_time'],$b['end_time']))) return false;
        }
        return $state['breaks'] || $this->availableBreaks($state,$tasks)!==null;
    }

    private function chooseTeacher(SchoolClass $class,Subject $subject,array $tasks): User
    {
        $rules=app(TeacherManagementService::class);
        $pool=collect($this->pool)->sortBy(fn($s)=>[$s['teacher']->id===$subject->teacher_id?0:1,count($this->committed[$s['teacher']->id]??[])]);
        foreach($pool as $state) if($this->fitsTeacher($state,$class,$subject,$tasks)) {
            if(!$state['breaks']) { $rules->saveBreaks($state['teacher'],$this->availableBreaks($state,$tasks)); $this->pool[$state['teacher']->id]=$this->teacherState($state['teacher']); }
            return $state['teacher'];
        }
        $teacher=new User;
        $teacher->forceFill(['name'=>'Sample '.$class->program.' '.$subject->name.' '.(count($this->createdTeachers)+1),'email'=>'demo-timetable-'.Str::uuid().'@example.test','role'=>'teacher','program'=>$class->program,'status'=>'active','gender'=>'other','password'=>Str::random(48),'profile_number'=>'DEMO-'.strtoupper(Str::random(10))])->save();
        $periods=[];
        foreach($rules::DAYS as $day) foreach($this->programHours[$class->program] as [$start,$end]) $periods[]=['weekday'=>$day,'start_time'=>$start,'end_time'=>$end,'unavailable'=>false];
        $rules->saveProfile($teacher,[$subject->name],1200,$periods);
        $state=$this->teacherState($teacher);
        if(!$this->fitsTeacher($state,$class,$subject,$tasks)) throw new \RuntimeException('A qualified sample teacher cannot cover '.$class->label.' '.$subject->name.' within the school working hours/break rules.');
        $rules->saveBreaks($teacher,$this->availableBreaks($state,$tasks));
        $this->pool[$teacher->id]=$this->teacherState($teacher);
        $this->createdTeachers[]=['id'=>$teacher->id,'name'=>$teacher->name,'program'=>$teacher->program,'subject'=>$subject->name];
        return $teacher;
    }
}
