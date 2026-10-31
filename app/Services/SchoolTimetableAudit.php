<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, SchoolClass};
use App\Support\AcademicYear;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SchoolTimetableAudit
{
    public function report(): array
    {
        $roomReport = app(SchoolRoomAssignmentRepair::class)->scan();
        $lessons = Schedule::with(['schoolClass','subject','teacher','classroom'])->get();
        $classes = SchoolClass::with(['subjects','classGroup'])->get();
        $missing = []; $unknown = []; $invalid = []; $reservations = [];
        foreach ($classes as $class) if ($class->subjects->isEmpty()) $unknown[] = ['program'=>$class->program,'class'=>$class->label,'cohort_id'=>$class->id,'subject'=>null,'required_minutes'=>null,'scheduled_minutes'=>0,'reason'=>'No curriculum subjects are linked to this existing cohort.'];
        foreach ($classes as $class) foreach ($class->timetableOwner()->subjects as $subject) {
            $years = $lessons->where('school_class_id',$class->timetableOwnerId())->pluck('academic_year')->unique();
            if ($years->isEmpty()) $years = collect([null]);
            foreach ($years as $year) {
                $actual = $lessons->where('subject_id',$subject->id)->filter(fn ($s)=>AcademicYear::overlaps($s->academic_year,$year))->sum(fn ($s)=>TeacherManagementService::duration($s->starts_at,$s->ends_at));
                $entry = ['program'=>$class->program,'class'=>$class->label,'cohort_id'=>$class->id,'academic_year'=>$year,'subject'=>$subject->name,'subject_id'=>$subject->id,'required_minutes'=>$subject->required_weekly_minutes,'scheduled_minutes'=>$actual];
                if ($subject->required_weekly_minutes === null) $unknown[] = $entry + ['reason'=>'Approved weekly curriculum hours are missing; empty periods cannot be classified as intentional free time.'];
                elseif ($actual < $subject->required_weekly_minutes) $missing[] = $entry + ['missing_minutes'=>$subject->required_weekly_minutes-$actual];
            }
        }
        foreach ($lessons as $lesson) {
            try { app(LessonManagementService::class)->validate($lesson); }
            catch (ValidationException|ModelNotFoundException $e) {
                $invalid[] = ['lesson_id'=>$lesson->id,'program'=>$lesson->schoolClass?->program,'class'=>$lesson->schoolClass?->label,'academic_year'=>$lesson->academic_year,'weekday'=>$lesson->day,'starts_at'=>$lesson->starts_at,'ends_at'=>$lesson->ends_at,'teacher'=>$lesson->teacher?->name,'classroom_id'=>$lesson->classroom_id,'physical_room'=>$lesson->classroom?->room_number,'recorded_room'=>$lesson->room,'reason'=>$e instanceof ModelNotFoundException ? 'Physical classroom, cohort, subject or teacher reference is missing.' : $e->getMessage()];
            }
        }
        foreach (ClassroomBooking::with(['classGroup','classSession','classroom','teacher'])->get() as $booking) {
            $cohorts = $classes->where('class_group_id',$booking->class_group_id)->where('session',$booking->classSession?->session);
            if ($cohorts->isNotEmpty()) continue;
            $reservations[] = ['reservation_id'=>$booking->id,'program'=>$booking->classGroup?->program,'class'=>$booking->classGroup?->display_name,'weekday'=>$booking->day_of_week,'starts_at'=>$booking->starts_at,'ends_at'=>$booking->ends_at,'teacher'=>$booking->teacher?->name,'classroom_id'=>$booking->classroom_id,'physical_room'=>$booking->classroom?->room_number,'reason'=>'No established student cohort is linked to this class identity and session.'];
        }
        $sessionIssues = [];
        foreach (ClassGroup::with('sessions')->where('program','Chinese')->get() as $group) {
            if (! $group->sessions->contains(fn ($s)=>TeacherManagementService::minute($s->starts_at) <= 18*60+30 && TeacherManagementService::minute($s->ends_at) >= 19*60+30)) $sessionIssues[] = ['program'=>'Chinese','class'=>$group->display_name,'weekday'=>'Monday–Friday','starts_at'=>'18:30','ends_at'=>'19:30','teacher'=>null,'physical_room'=>null,'reason'=>'No existing class-session record covers the second Chinese evening hour. Confirm the actual cohort/session; valid earlier sessions were preserved.'];
        }
        $conflicts = collect($roomReport['conflicts']);
        return ['checked_at'=>now('Asia/Phnom_Penh')->toIso8601String(),'total_classes_checked'=>$roomReport['total_classes'],'total_cohorts_checked'=>$classes->count(),'total_lessons_checked'=>$roomReport['total_lessons'],'active_lessons_checked'=>$lessons->count(),'programs'=>ClassGroup::all()->groupBy('program')->map->count()->all(),'classroom_assignments_repaired'=>\Illuminate\Support\Facades\DB::table('lesson_room_corrections')->count(),'remaining_classroom_issues'=>$roomReport['affected_lessons'],'teacher_conflicts'=>$conflicts->where('resource','teacher')->values()->all(),'student_cohort_conflicts'=>$conflicts->where('resource','student_cohort')->values()->all(),'classroom_conflicts'=>$conflicts->where('resource','classroom')->values()->all(),'room_issues'=>array_merge($roomReport['verified'],$roomReport['manual_review']),'missing_required_lessons'=>$missing,'unknown_curriculum'=>$unknown,'invalid_lessons'=>$invalid,'unresolved_room_reservations'=>$reservations,'session_configuration_issues'=>$sessionIssues,'chinese_evening_sessions'=>\App\Models\ClassSession::whereHas('classGroup',fn ($q)=>$q->where('program','Chinese'))->get(['id','class_group_id','session','starts_at','ends_at'])->toArray()];
    }
}
