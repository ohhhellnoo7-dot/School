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
        $classes = SchoolClass::with(['subjects','classGroup.sessions'])->where('program', 'Khmer')->get();
        $groups = ClassGroup::where('program', 'Khmer')->where('status','active')->get();
        foreach ($groups as $group) {
            if ($group->sessions()->whereIn('session', ['Morning','Afternoon'])->count() === 0) $blockers[] = ['type' => 'cohort', 'message' => $group->display_name.': no confirmed morning or afternoon cohort session.'];
        }
        foreach ($classes as $class) {
            foreach ($class->subjects as $subject) if ($subject->required_weekly_minutes !== null && (int) $subject->required_weekly_minutes % \App\Support\KhmerLessonDuration::minutes($subject) !== 0) $blockers[] = ['type' => 'duration', 'message' => $class->label.' / '.$subject->name.': weekly hours must fit complete '.(\App\Support\KhmerLessonDuration::minutes($subject) / 60).'-hour blocks.'];
            if ($class->weekly_relaxation_minutes !== 180) $blockers[] = ['type' => 'relaxation', 'message' => $class->label.': approve three weekly relaxation hours in Scheduling Settings.'];
            $demand = $class->subjects->sum('required_weekly_minutes') + (int) $class->weekly_relaxation_minutes;
            if ($demand > 1200) $blockers[] = ['type' => 'capacity', 'message' => $class->label.': demand exceeds session capacity by '.($demand-1200).' minutes.'];
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
        return ['status' => $blockers ? 'blocked' : 'draft', 'academic_year' => $academicYear, 'created_at' => now('Asia/Phnom_Penh')->toIso8601String(), 'diagnostics' => $diagnostics, 'blockers' => $blockers, 'plan' => $plan];
    }
}
