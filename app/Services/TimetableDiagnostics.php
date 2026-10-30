<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassSession, Schedule, SchoolClass, User};

class TimetableDiagnostics
{
    public function report(): array
    {
        $rules = app(TeacherManagementService::class);
        $groups = ClassGroup::where('program', 'Khmer')->where('status', 'active')->get();
        $classes = SchoolClass::with('subjects')->where('program', 'Khmer')->get();
        $lessons = Schedule::with(['schoolClass', 'subject', 'teacher', 'classroom'])->get();
        $issues = [];
        foreach (range(7, 12) as $grade) foreach (range('A', 'E') as $section) {
            $matches = $groups->where('grade', $grade)->where('section', $section);
            if ($matches->count() !== 1) $issues[] = ['type' => 'identity', 'message' => $grade.$section.': expected one active class identity, found '.$matches->count()];
        }
        foreach ($groups as $group) foreach (ClassSession::where('class_group_id', $group->id)->get() as $session) {
            $cohorts = $classes->where('class_group_id', $group->id)->where('session', $session->session);
            if ($cohorts->count() !== 1) $issues[] = ['type' => 'cohort', 'message' => $group->display_name.' '.$session->session.': confirm exactly one linked cohort (found '.$cohorts->count().').'];
        }
        foreach ($classes as $class) {
            if (! $class->class_group_id || ! $groups->contains('id', $class->class_group_id)) $issues[] = ['type' => 'cohort', 'message' => $class->label.': missing active class identity.'];
            if ($class->subjects->isEmpty() || $class->subjects->contains(fn ($s) => $s->required_weekly_minutes === null)) $issues[] = ['type' => 'curriculum', 'message' => $class->label.': approved weekly subject hours are missing.'];
            if ((int) $class->weekly_relaxation_minutes !== 180) $issues[] = ['type' => 'relaxation', 'message' => $class->label.': configure three one-hour student relaxation periods.'];
            foreach ($class->subjects as $subject) {
                $actual = $lessons->where('subject_id', $subject->id)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at));
                if ($subject->required_weekly_minutes !== null && $actual !== (int) $subject->required_weekly_minutes) $issues[] = ['type' => 'coverage', 'message' => $class->label.' / '.$subject->name.': required '.$subject->required_weekly_minutes.' minutes, scheduled '.$actual.'.'];
            }
        }
        foreach ($lessons as $index => $lesson) {
            $label = 'Lesson #'.$lesson->id.': ';
            if (! $lesson->classroom) $issues[] = ['type' => 'room', 'lesson_id' => $lesson->id, 'message' => $label.'physical classroom ID is missing or invalid; recorded label '.$lesson->room.'.'];
            elseif (RoomLessonService::roomKey($lesson->room) !== RoomLessonService::roomKey($lesson->classroom->room_number)) $issues[] = ['type' => 'room', 'lesson_id' => $lesson->id, 'message' => $label.'room label differs from physical classroom.'];
            if (! $lesson->isRelaxation() && (! $lesson->teacher || ! $lesson->subject)) $issues[] = ['type' => 'reference', 'lesson_id' => $lesson->id, 'message' => $label.'missing teacher or subject.'];
            if ($lesson->schoolClass?->program === 'Khmer') {
                [$from, $to] = SchoolClass::SESSION_HOURS[$lesson->schoolClass->session] ?? ['00:00', '00:00'];
                if (! in_array($lesson->day, $rules::DAYS, true) || ! in_array($rules::duration($lesson->starts_at, $lesson->ends_at), [60, 120], true) || $rules::minute($lesson->starts_at) < $rules::minute($from) || $rules::minute($lesson->ends_at) > $rules::minute($to)) $issues[] = ['type' => 'session', 'lesson_id' => $lesson->id, 'message' => $label.'outside permitted session or duration.'];
            }
            foreach ($lessons->slice($index + 1) as $other) {
                if ($lesson->day !== $other->day || ! $rules::overlaps($lesson->starts_at, $lesson->ends_at, $other->starts_at, $other->ends_at)) continue;
                foreach (['teacher_id' => 'teacher', 'school_class_id' => 'cohort', 'classroom_id' => 'room'] as $key => $type) if ($lesson->$key && $lesson->$key === $other->$key) $issues[] = ['type' => $type.'_overlap', 'lesson_id' => $lesson->id, 'message' => $label.'overlaps lesson #'.$other->id.' for '.$type.'.'];
            }
        }
        foreach (User::where('role', 'teacher')->where('program', 'Khmer')->get() as $teacher) {
            $work = $rules->workload($teacher);
            if ($work['subjects']->count() > 2) $issues[] = ['type' => 'qualification', 'message' => $teacher->name.': more than two approved subject assignments.'];
            if ($work['weekly'] > $work['limit']) $issues[] = ['type' => 'workload', 'message' => $teacher->name.': weekly workload exceeded.'];
            $breaks = $rules->breaks($teacher);
            if ($breaks->count() !== 3 || $breaks->pluck('weekday')->unique()->count() !== 3) $issues[] = ['type' => 'free_period', 'message' => $teacher->name.': three designated free periods on separate weekdays are not established.'];
        }
        return ['timezone' => 'Asia/Phnom_Penh', 'identities' => $groups->count(), 'cohorts' => $classes->count(), 'active_lessons' => $lessons->count(), 'missing_room_ids' => $lessons->whereNull('classroom_id')->count(), 'issues' => $issues, 'class_validation' => app(KhmerClassTimetableReport::class)->report()];
    }
}
