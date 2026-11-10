<?php
namespace App\Services;

use App\Models\{CurriculumProfile, SchoolClass, Subject, Schedule, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KhmerCurriculumService
{
    public const ALIASES = ['Khmer'=>'Khmer Literature', 'Computer Science'=>'ICT', 'Computer Studies'=>'ICT'];

    public function profileFor(SchoolClass $class): ?CurriculumProfile
    {
        if ($class->program !== 'Khmer') return null;
        $group = $class->classGroup;
        $grade = $group?->grade ?? (preg_match('/(\d+)/', $class->name, $match) ? (int) $match[1] : 0);
        $stream = $grade >= 11 ? ($group?->stream ?? '') : '';
        return CurriculumProfile::with('requirements')->where('program','Khmer')->where('grade',$grade)->where('stream',$stream)->first();
    }

    public function configure(SchoolClass $class): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($class) {
            $profile = $this->profileFor($class);
            if (! $profile) throw ValidationException::withMessages(['curriculum'=>$class->label.': choose a grade and study stream with a curriculum profile.']);
            if ($profile->requirements->sum('weekly_minutes') !== 1200) throw ValidationException::withMessages(['curriculum'=>$profile->label.': curriculum must total 20 hours per session.']);
            $subjects = $class->subjects()->get();
            $scheduled = Schedule::where('school_class_id',$class->id)->get()->groupBy('subject_id')->map(fn ($rows) => $rows->sum(fn ($s) => TeacherManagementService::duration($s->starts_at,$s->ends_at)));
            $used = []; $created = [];
            foreach ($profile->requirements as $requirement) {
                $subject = $subjects->filter(fn ($s) => (self::ALIASES[$s->name] ?? $s->name) === $requirement->name)
                    ->sortBy(fn ($s) => [-($scheduled[$s->id] ?? 0), $s->teacher_id ? 0 : 1, $s->id])->first();
                if (! $subject) {
                    $id = DB::table('subjects')->insertGetId(['school_class_id'=>$class->id,'name'=>$requirement->name,'code'=>'KH-CUR-'.$class->id.'-'.$requirement->id,'created_at'=>now(),'updated_at'=>now()]);
                    $subject = Subject::findOrFail($id); $created[] = $id;
                }
                DB::table('subjects')->where('id',$subject->id)->update(['name'=>$requirement->name,'required_weekly_minutes'=>$requirement->weekly_minutes,'lesson_minutes'=>$requirement->lesson_minutes,'curriculum_requirement_id'=>$requirement->id,'updated_at'=>now()]);
                $used[] = $subject->id;
            }
            // Retain every legacy subject, teacher assignment, mark and lesson for history.
            DB::table('subjects')->where('school_class_id',$class->id)->whereNotIn('id',$used)->update(['required_weekly_minutes'=>0,'curriculum_requirement_id'=>null,'updated_at'=>now()]);
            DB::table('school_classes')->where('id',$class->id)->update(['weekly_relaxation_minutes'=>0]);
            return ['class_id'=>$class->id,'profile_id'=>$profile->id,'subject_count'=>count($used),'created_subjects'=>$created,'source'=>'provisional_scholar_configuration','configured'=>count($used)];
        });
    }

    public function syncAll(): array
    {
        return app(TeacherManagementService::class)->locked(function () {
            // Canonicalize existing qualification names only; never invent qualifications.
            $teacherIds = User::where('role','teacher')->where('program','Khmer')->pluck('id');
            foreach (DB::table('teacher_qualifications')->whereIn('teacher_id',$teacherIds)->get() as $qualification) {
                $canonical = self::ALIASES[$qualification->subject_name] ?? null;
                if (! $canonical) continue;
                if (DB::table('teacher_qualifications')->where('teacher_id',$qualification->teacher_id)->where('subject_name',$canonical)->exists()) DB::table('teacher_qualifications')->where('id',$qualification->id)->delete();
                else DB::table('teacher_qualifications')->where('id',$qualification->id)->update(['subject_name'=>$canonical]);
            }
            // Rename aliases throughout Khmer history so schedules and qualifications agree.
            $ids = SchoolClass::where('program','Khmer')->pluck('id');
            foreach (self::ALIASES as $old=>$name) DB::table('subjects')->whereIn('school_class_id',$ids)->where('name',$old)->update(['name'=>$name,'updated_at'=>now()]);
            return SchoolClass::with('classGroup')->where('program','Khmer')->get()->map(fn ($class) => $this->configure($class))->all();
        });
    }

    public function readiness(?string $year = null): array
    {
        $rules = app(TeacherManagementService::class);
        $classes = SchoolClass::with(['subjects','classGroup'])->where('program','Khmer')->get();
        $teachers = User::where('role','teacher')->where('status','active')->where('program','Khmer')->get();
        $year ??= now()->year.'-'.(now()->year+1);
        $outside = Schedule::whereNotIn('school_class_id',$classes->modelKeys())->where(fn ($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->get();
        $staff = $teachers->map(function ($teacher) use ($rules,$outside) {
            $other = $outside->where('teacher_id',$teacher->id);
            $periods = $rules->periods($teacher); $breaks = $rules->breaks($teacher);
            $slots = 0;
            foreach ($rules::DAYS as $day) foreach ([7,8,9,10,13,14,15,16] as $hour) {
                $start = sprintf('%02d:00',$hour); $end = sprintf('%02d:00',$hour+1);
                if ($periods->contains(fn ($p)=>$p->weekday===$day && ! $p->unavailable && $rules::minute($p->start_time)<=$rules::minute($start) && $rules::minute($p->end_time)>=$rules::minute($end)) && ! $periods->contains(fn ($p)=>$p->weekday===$day && $p->unavailable && $rules::overlaps($start,$end,$p->start_time,$p->end_time)) && ! $other->contains(fn ($s)=>$s->day===$day && $rules::overlaps($start,$end,$s->starts_at,$s->ends_at)) && ! $breaks->contains(fn ($b)=>$b->weekday===$day && $rules::overlaps($start,$end,$b->start_time,$b->end_time))) $slots++;
            }
            return ['id'=>$teacher->id,'name'=>$teacher->name,'qualifications'=>$rules->qualifications($teacher)->all(),'minutes'=>max(0,min($slots*60,$rules->limit($teacher)-$other->sum(fn ($s)=>$rules::duration($s->starts_at,$s->ends_at))))];
        });
        $blockers = []; $demand = [];
        foreach ($classes as $class) {
            $profile = $this->profileFor($class);
            $active = $class->subjects->whereNotNull('curriculum_requirement_id');
            if (! $profile || $active->count() !== $profile->requirements->count() || $active->sum('required_weekly_minutes') !== 1200) $blockers[] = $class->label.': curriculum is missing or does not fill all 20 weekly hours.';
            foreach ($active as $subject) $demand[$subject->name] = ($demand[$subject->name] ?? 0) + $subject->required_weekly_minutes;
        }
        $coverage = [];
        foreach ($demand as $name=>$minutes) {
            $eligible = $staff->filter(fn ($t)=>in_array($name,$t['qualifications'],true));
            $capacity = $eligible->sum('minutes');
            $coverage[] = ['subject'=>$name,'required_hours'=>$minutes/60,'qualified_teachers'=>$eligible->count(),'capacity_hours'=>$capacity/60];
            if ($capacity < $minutes) $blockers[] = $name.': requires '.($minutes/60).' hours; '.$eligible->count().' active qualified teachers offer at most '.($capacity/60).' hours before competing subject demands.';
        }
        if ($staff->sum('minutes') < $classes->count()*1200) $blockers[] = 'Total available teaching hours are below the required '.($classes->count()*20).' hours.';
        return ['status'=>$blockers?'blocked':'ready_for_constraint_solver','academic_year'=>$year,'cohorts'=>$classes->count(),'required_hours'=>$classes->count()*20,'available_hours'=>$staff->sum('minutes')/60,'coverage'=>$coverage,'blockers'=>$blockers,'curriculum_status'=>'provisional','planned_staff_count'=>$teachers->filter(fn($t)=>$t->requiresTeacherConfirmation())->count()];
    }
}
