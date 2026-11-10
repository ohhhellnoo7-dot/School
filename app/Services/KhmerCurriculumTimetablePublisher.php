<?php
namespace App\Services;
use App\Models\{SchoolClass,Schedule,User,ClassroomBooking};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KhmerCurriculumTimetablePublisher
{
    public function publish(array $plan): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($plan) {
            if (! in_array($plan['status']??'', ['FEASIBLE','OPTIMAL'],true) || empty($plan['rows'])) throw new RuntimeException('A complete solved timetable is required.');
            if (! hash_equals($plan['fingerprint'],app(RotationTimetableGenerator::class)->fingerprint())) throw new RuntimeException('School data changed after solving. Generate a fresh draft.');
            $rules=app(TeacherManagementService::class); $year=$plan['academic_year'] ?? $plan['rows'][0]['academic_year'];
            if (! preg_match('/^\d{4}-\d{4}$/',$year)) throw new RuntimeException('Invalid academic year.');
            $classes=SchoolClass::with(['subjects','classGroup'])->withCount(['students as enrolled_count'=>fn($q)=>$q->where('status','active')])->where('program','Khmer')->get()->keyBy('id');
            $subjects=$classes->flatMap->subjects->keyBy('id');
            $teachers=User::where('role','teacher')->where('program','Khmer')->where('status','active')->get()->keyBy('id');
            $bookings=ClassroomBooking::with(['classSession','classroom'])->get();
            $outside=Schedule::with('subject')->whereNotIn('school_class_id',$classes->modelKeys())->where(fn($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->get();
            if ($outside->contains(fn ($lesson)=>!$lesson->classroom_id)) throw new RuntimeException('Existing outside lessons lack physical classroom IDs.');
            $outsideHash=hash('sha256',$outside->toJson());
            $state=[]; $loads=[]; $classSlots=[]; $teacherSlots=[]; $roomSlots=[]; $minutes=[]; $assignments=[]; $names=[];
            foreach($teachers as $teacher) {
                $state[$teacher->id]=['qualifications'=>$rules->qualifications($teacher),'periods'=>$rules->periods($teacher),'breaks'=>$rules->breaks($teacher)];
                $names[$teacher->id]=array_fill_keys($outside->where('teacher_id',$teacher->id)->pluck('subject.name')->filter()->unique()->all(),true);
                if ($state[$teacher->id]['breaks']->count()!==$rules->requiredBreaks($teacher)) throw new RuntimeException('Configure required teacher breaks before publishing.');
                $loads[$teacher->id]=$outside->where('teacher_id',$teacher->id)->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at));
            }
            foreach($plan['rows'] as $row) {
                $class=$classes->get($row['school_class_id']); $subject=$subjects->get($row['subject_id']); $teacher=$teachers->get($row['teacher_id']);
                if(($row['entry_type']??null)!=='academic' || !$class || !$subject || !$subject->curriculum_requirement_id || $subject->school_class_id!==$class->id || !$teacher || $row['academic_year']!==$year) throw new RuntimeException('Invalid timetable reference.');
                $duration=$rules::duration($row['starts_at'],$row['ends_at']); [$start,$end]=$class->sessionHours();
                if($duration!==$subject->lesson_minutes || !in_array($row['day'],$rules::DAYS,true) || $row['starts_at']<$start || $row['ends_at']>$end || ($rules::minute($row['starts_at'])-$rules::minute($start))%60) throw new RuntimeException('Invalid lesson block.');
                $reservation=$bookings->first(fn($b)=>$b->class_group_id===$class->class_group_id && $b->classSession?->session===$class->session && $b->classroom_id===$row['classroom_id'] && $b->day_of_week===$row['day'] && $rules::minute($b->starts_at)<=$rules::minute($row['starts_at']) && $rules::minute($b->ends_at)>=$rules::minute($row['ends_at']));
                if(!$reservation || $reservation->classroom->status!=='active' || $reservation->classroom->capacity<$class->enrolled_count || $row['room']!==$reservation->classroom->room_number) throw new RuntimeException('Invalid classroom reservation or capacity.');
                $info=$state[$teacher->id]; $periods=$info['periods']->where('weekday',$row['day']);
                if(!$info['qualifications']->contains($subject->name) || !$periods->contains(fn($p)=>!$p->unavailable && $rules::minute($p->start_time)<=$rules::minute($row['starts_at']) && $rules::minute($p->end_time)>=$rules::minute($row['ends_at'])) || $periods->contains(fn($p)=>$p->unavailable && $rules::overlaps($p->start_time,$p->end_time,$row['starts_at'],$row['ends_at'])) || $info['breaks']->contains(fn($b)=>$b->weekday===$row['day'] && $rules::overlaps($b->start_time,$b->end_time,$row['starts_at'],$row['ends_at']))) throw new RuntimeException('Teacher qualification or availability conflict.');
                if($outside->contains(fn($s)=>$s->day===$row['day'] && ($s->teacher_id===$teacher->id || $s->classroom_id===$row['classroom_id']) && $rules::overlaps($s->starts_at,$s->ends_at,$row['starts_at'],$row['ends_at']))) throw new RuntimeException('Other-program timetable conflict.');
                if(isset($assignments[$subject->id]) && $assignments[$subject->id]!==$teacher->id) throw new RuntimeException('A class subject must have one teacher.');
                $assignments[$subject->id]=$teacher->id; $names[$teacher->id][$subject->name]=true;
                $minutes[$subject->id]=($minutes[$subject->id]??0)+$duration; $loads[$teacher->id]+=$duration;
                for($m=$rules::minute($row['starts_at']);$m<$rules::minute($row['ends_at']);$m+=60) {
                    $slot=$row['day'].':'.$m;
                    if(isset($classSlots[$class->id][$slot]) || isset($teacherSlots[$teacher->id][$slot]) || isset($roomSlots[$row['classroom_id']][$slot])) throw new RuntimeException('Overlapping teacher, cohort or classroom lessons.');
                    $classSlots[$class->id][$slot]=true; $teacherSlots[$teacher->id][$slot]=true; $roomSlots[$row['classroom_id']][$slot]=true;
                }
            }
            foreach($classes as $class) {
                if(count($classSlots[$class->id]??[])!==20) throw new RuntimeException('Student timetable contains free periods.');
                foreach($class->subjects as $subject) if(($minutes[$subject->id]??0)!==(int)$subject->required_weekly_minutes) throw new RuntimeException('Subject requirement mismatch: '.$subject->id);
            }
            foreach($teachers as $teacher) if(($loads[$teacher->id]??0)>$rules->limit($teacher) || count($names[$teacher->id]??[])>$rules->subjectLimit($teacher)) throw new RuntimeException('Teacher workload or subject limit exceeded.');
            $backup=app(TimetableBackup::class)->create();
            Schedule::whereIn('school_class_id',$classes->modelKeys())->where(fn($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->update(['cancelled_at'=>now()]);
            foreach($assignments as $subjectId=>$teacherId) DB::table('subjects')->where('id',$subjectId)->update(['teacher_id'=>$teacherId,'updated_at'=>now()]);
            foreach(array_chunk($plan['rows'],100) as $chunk) DB::table('schedules')->insert(array_map(fn($row)=>$row+['generated_at'=>now(),'created_at'=>now(),'updated_at'=>now()],$chunk));
            if(hash('sha256',Schedule::with('subject')->whereNotIn('school_class_id',$classes->modelKeys())->where(fn($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->get()->toJson())!==$outsideHash) throw new RuntimeException('Other-program timetable changed.');
            return ['backup'=>$backup,'cohorts'=>$classes->count(),'lesson_count'=>count($plan['rows']),'assigned_teachers'=>count($names),'student_free_periods'=>0,'curriculum_status'=>'provisional','planned_staff_ids'=>$plan['planned_staff_ids']??[]];
        });
    }
}
