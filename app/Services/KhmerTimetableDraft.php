<?php

namespace App\Services;

use App\Models\{ClassGroup, SchoolClass};
use Illuminate\Validation\ValidationException;

/** A complete-school draft reports every missing prerequisite before planning. */
class KhmerTimetableDraft
{
    public function prepare(?string $academicYear = null): array
    {
        $diagnostics = app(TimetableDiagnostics::class)->report();
        $blockers = collect($diagnostics['issues'])->whereIn('type', ['identity', 'cohort', 'curriculum', 'qualification'])->values()->all();
        $capacity = app(KhmerTeachingCapacity::class)->report();
        if ($capacity['shortage_hours'] > 0) $blockers[] = ['type' => 'staffing', 'message' => 'INCOMPLETE: '.$capacity['cohorts'].' Khmer cohorts need '.$capacity['required_hours'].' teaching hours weekly; '.$capacity['teachers'].' teachers can provide at most '.$capacity['available_hours'].' hours after availability, designated breaks, workload limits and other-program lessons. At least '.$capacity['shortage_hours'].' additional qualified teaching hours are needed.'];
        $classes = SchoolClass::with(['subjects','classGroup.sessions'])->where('program', 'Khmer')->get();
        $groups = ClassGroup::where('program', 'Khmer')->where('status','active')->get();
        foreach ($groups as $group) {
            if ($group->sessions()->whereIn('session', ['Morning','Afternoon'])->count() === 0) $blockers[] = ['type' => 'cohort', 'message' => $group->display_name.': no confirmed morning or afternoon cohort session.'];
        }
        foreach ($classes as $class) {
            foreach ($class->subjects as $subject) if ($subject->required_weekly_minutes !== null && (int) $subject->required_weekly_minutes % \App\Support\KhmerLessonDuration::minutes($subject) !== 0) $blockers[] = ['type' => 'duration', 'message' => $class->label.' / '.$subject->name.': weekly hours must fit complete '.(\App\Support\KhmerLessonDuration::minutes($subject) / 60).'-hour blocks.'];
            $demand = $class->subjects->sum('required_weekly_minutes');
            if ($demand !== 1200) $blockers[] = ['type' => 'capacity', 'message' => $class->label.': INCOMPLETE; approved curriculum totals '.($demand / 60).' hours; full session requires 20 hours (difference '.(($demand-1200) / 60).' hours). Administrator must adjust approved curriculum.'];
        }
        $plan = null;
        if (! $blockers) {
            try {
                $plan = app(UnifiedTimetableGenerator::class)->preview($classes->modelKeys(), $academicYear);
                if ($plan['validation']['valid_identities'] !== 30) {
                    foreach ($plan['validation']['identities'] as $identity) if ($identity['status'] !== 'valid') {
                        foreach ($identity['issues'] as $message) $blockers[] = ['type' => 'validation', 'message' => $identity['class'].': '.$message];
                        foreach ($identity['cohorts'] as $cohort) foreach ($cohort['issues'] as $message) $blockers[] = ['type' => 'validation', 'message' => $identity['class'].' '.$cohort['session'].': '.$message];
                    }
                    $plan = null;
                } else $plan['complete_school'] = true;
            }
            catch (ValidationException $e) { foreach ($e->errors() as $messages) foreach ($messages as $message) $blockers[] = ['type' => 'generation', 'message' => $message]; }
        }
        return ['status' => $blockers ? 'blocked' : 'draft', 'academic_year' => $academicYear, 'created_at' => now('Asia/Phnom_Penh')->toIso8601String(), 'teaching_capacity' => $capacity, 'diagnostics' => $diagnostics, 'blockers' => $blockers, 'plan' => $plan];
    }
}
