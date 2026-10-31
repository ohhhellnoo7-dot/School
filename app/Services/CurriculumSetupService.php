<?php

namespace App\Services;

use App\Models\{Schedule, SchoolClass, Subject};
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\DB;

/** Initialize missing school settings; never change existing weekly subject targets. */
class CurriculumSetupService
{
    public function configure(SchoolClass $class): array
    {
        $class = $class->timetableOwner();
        return app(TeacherManagementService::class)->locked(function () use ($class) {
            $class->refresh()->load('subjects');
            $created = [];
            if ($class->subjects->isEmpty()) {
                $template = SchoolClass::with('subjects')->where('program', $class->program)->whereHas('subjects')
                    ->get()->sortBy(fn ($candidate) => [$candidate->name === $class->name ? 0 : 1, $candidate->session === $class->session ? 0 : 1, $candidate->id])->first();
                if (! $template) throw new \RuntimeException($class->label.': no existing program subject catalog to copy.');
                foreach ($template->subjects->unique('name') as $subject) {
                    $copy = Subject::create(['school_class_id' => $class->id, 'name' => $subject->name, 'code' => 'CUR-'.$class->id.'-'.$subject->id, 'lesson_minutes' => $subject->lesson_minutes ?? 60]);
                    $created[] = $copy->id;
                }
                $class->load('subjects');
            }
            $unknown = $class->subjects->whereNull('required_weekly_minutes');
            if ($unknown->isEmpty()) return ['class_id' => $class->id, 'configured' => 0, 'created_subjects' => $created];
            [$start, $end] = $class->sessionHours();
            $capacity = TeacherManagementService::duration($start, $end) * count(TeacherManagementService::DAYS);
            $lessons = Schedule::where('school_class_id', $class->id)->where(fn ($q) => $q->where('academic_year', now()->year.'-'.(now()->year + 1))->orWhereNull('academic_year'))->get();
            // A full, non-overlapping existing study week is the strongest available school allocation.
            $covered = [];
            foreach ($lessons as $lesson) {
                if (! in_array($lesson->day, TeacherManagementService::DAYS, true)) continue;
                for ($minute = max(TeacherManagementService::minute($start), TeacherManagementService::minute($lesson->starts_at)); $minute < min(TeacherManagementService::minute($end), TeacherManagementService::minute($lesson->ends_at)); $minute++) $covered[$lesson->day.':'.$minute] = true;
            }
            $actual = $class->subjects->mapWithKeys(fn ($s) => [$s->id => $lessons->where('subject_id', $s->id)->sum(fn ($l) => TeacherManagementService::duration($l->starts_at, $l->ends_at))]);
            $relaxation = $lessons->where('entry_type', 'relaxation')->sum(fn ($l) => TeacherManagementService::duration($l->starts_at, $l->ends_at));
            $complete = count($covered) === $capacity && $actual->sum() + $relaxation === $capacity
                && $unknown->count() === $class->subjects->count()
                && $unknown->every(fn ($s) => $actual[$s->id] % KhmerLessonDuration::minutes($s) === 0);
            if ($complete) {
                $targets = $actual->all();
                $class->update(['weekly_relaxation_minutes' => $relaxation]);
                $source = 'existing_complete_timetable';
            } else {
                $budget = $capacity - (int) $class->weekly_relaxation_minutes - $class->subjects->whereNotNull('required_weekly_minutes')->sum('required_weekly_minutes');
                if ($budget < 0 || $budget % 60 !== 0) throw new \RuntimeException($class->label.': existing settings exceed session capacity or use partial hours.');
                $targets = $unknown->mapWithKeys(fn ($s) => [$s->id => 0])->all();
                // Duplicate catalog rows do not double teaching demand for the same subject.
                $distinct = $unknown->unique('name')->sortBy(fn ($s) => [$actual[$s->id] > 0 ? 0 : 1, in_array(mb_strtolower($s->name), ['mathematics','khmer','english language'], true) ? 0 : 1, $s->id]);
                while ($budget > 0) {
                    $placed = false;
                    foreach ($distinct as $subject) {
                        $block = KhmerLessonDuration::minutes($subject);
                        if (! in_array($block, [60, 120], true) || $block > $budget) continue;
                        $targets[$subject->id] += $block;
                        $budget -= $block;
                        $placed = true;
                    }
                    if (! $placed) throw new \RuntimeException($class->label.': remaining study time cannot fit the catalog lesson durations.');
                }
                $source = 'balanced_school_defaults';
            }
            foreach ($unknown as $subject) {
                KhmerLessonDuration::validateWeeklyHours($subject, $targets[$subject->id]);
                DB::table('subjects')->where('id', $subject->id)->whereNull('required_weekly_minutes')->update([
                    'required_weekly_minutes' => $targets[$subject->id],
                    'lesson_minutes' => KhmerLessonDuration::minutes($subject), 'updated_at' => now(),
                ]);
            }
            return ['class_id' => $class->id, 'class' => $class->label, 'configured' => $unknown->count(), 'created_subjects' => $created, 'source' => $source, 'targets' => $targets, 'weekly_relaxation_minutes' => (int) $class->weekly_relaxation_minutes];
        });
    }
}
