<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, SchoolClass};
use App\Support\KhmerLessonDuration;

/** Always returns 7A through 12E, including identities with no established cohort. */
class KhmerClassTimetableReport
{
    public function report(): array
    {
        $rules = app(TeacherManagementService::class);
        $groups = ClassGroup::with('sessions')->where('program', 'Khmer')->where('status', 'active')->get();
        $classes = SchoolClass::with(['subjects.teacher', 'classGroup'])->where('program', 'Khmer')->get();
        $all = Schedule::with(['subject', 'teacher', 'classroom', 'schoolClass'])->get();
        $bookings = ClassroomBooking::with('classSession')->get();
        $teacherState = [];
        $identities = [];
        foreach (range(7, 12) as $grade) foreach (range('A', 'E') as $section) {
            $matching = $groups->where('grade', $grade)->where('section', $section);
            $cohorts = [];
            foreach ($classes->whereIn('class_group_id', $matching->modelKeys()) as $class) {
                $lessons = $all->where('school_class_id', $class->id);
                $issues = []; $unscheduled = []; $subjectRows = [];
                $studentCount = $class->students()->where('status', 'active')->count();
                if (! in_array($class->session, ['Morning', 'Afternoon'], true)) $issues[] = 'Cohort must use the morning or afternoon session.';
                if (! $class->classGroup?->sessions->contains('session', $class->session)) $issues[] = 'No class session is linked to this cohort.';
                if ($class->subjects->isEmpty()) $issues[] = 'No curriculum subjects are linked to this cohort.';
                if ($lessons->where('entry_type', 'relaxation')->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) !== (int) $class->weekly_relaxation_minutes) $issues[] = 'Scheduled relaxation does not match the cohort requirement.';
                foreach ($class->subjects as $subject) {
                    $actual = $lessons->where('subject_id', $subject->id)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at));
                    $required = $subject->required_weekly_minutes;
                    $duration = KhmerLessonDuration::minutes($subject);
                    $reason = null;
                    if ($required === null) $reason = 'Approved weekly subject hours are missing.';
                    elseif ((int) $required % $duration !== 0) $reason = 'Weekly hours cannot be divided into the required '.($duration / 60).'-hour blocks.';
                    elseif ($actual !== (int) $required) $reason = 'Required '.($required / 60).' hours; currently scheduled '.($actual / 60).' hours.';
                    if ($reason) { $issues[] = $subject->name.': '.$reason; $unscheduled[] = ['subject_id' => $subject->id, 'subject' => $subject->name, 'required_minutes' => $required, 'scheduled_minutes' => $actual, 'reason' => $reason]; }
                    $subjectRows[] = ['subject_id' => $subject->id, 'subject' => $subject->name, 'lesson_minutes' => $duration, 'required_minutes' => $required, 'scheduled_minutes' => $actual];
                }
                foreach ($lessons as $lesson) {
                    $prefix = 'Lesson #'.$lesson->id.': ';
                    [$from, $to] = SchoolClass::SESSION_HOURS[$class->session] ?? ['00:00', '00:00'];
                    if (! in_array($lesson->day, $rules::DAYS, true) || $rules::minute($lesson->starts_at) < $rules::minute($from) || $rules::minute($lesson->ends_at) > $rules::minute($to)) $issues[] = $prefix.'outside the permitted school session.';
                    $duration = $rules::duration($lesson->starts_at, $lesson->ends_at);
                    if (! $lesson->classroom) $issues[] = $prefix.'physical classroom ID is missing or invalid.';
                    else {
                        if ($lesson->classroom->status !== 'active') $issues[] = $prefix.'physical classroom is unavailable.';
                        if ($lesson->classroom->capacity < $studentCount) $issues[] = $prefix.'physical classroom capacity is insufficient.';
                        if ($bookings->contains(fn ($b) => $b->classroom_id === $lesson->classroom_id && ($b->class_group_id !== $class->class_group_id || $b->classSession?->session !== $class->session) && $b->day_of_week === $lesson->day && $rules::overlaps($b->starts_at, $b->ends_at, $lesson->starts_at, $lesson->ends_at))) $issues[] = $prefix.'physical classroom is reserved for another cohort.';
                        if (! in_array(RoomLessonService::roomKey($lesson->room), [RoomLessonService::roomKey($lesson->classroom->room_number), RoomLessonService::roomKey($lesson->classroom->name)], true)) $issues[] = $prefix.'recorded room label differs from the physical room ID.';
                    }
                    if ($lesson->isRelaxation()) {
                        if ($duration !== 60 || $lesson->teacher_id || $lesson->subject_id) $issues[] = $prefix.'relaxation must be one hour without a teacher-led subject.';
                    } elseif (! $lesson->subject || ! $lesson->teacher) $issues[] = $prefix.'teacher or subject is missing.';
                    else {
                        if ($lesson->subject->school_class_id !== $class->id) $issues[] = $prefix.'subject belongs to a different cohort.';
                        if ($duration !== KhmerLessonDuration::minutes($lesson->subject)) $issues[] = $prefix.$lesson->subject->name.' must use '.(KhmerLessonDuration::minutes($lesson->subject) / 60).' consecutive hours.';
                        $teacher = $lesson->teacher;
                        if (! isset($teacherState[$teacher->id])) $teacherState[$teacher->id] = ['qualifications' => $rules->qualifications($teacher), 'breaks' => $rules->breaks($teacher), 'workload' => $rules->workload($teacher)];
                        $state = $teacherState[$teacher->id];
                        if ($state['breaks']->count() !== 3 || $state['breaks']->pluck('weekday')->unique()->count() !== 3) $issues[] = $prefix.'teacher needs three designated one-hour free periods on separate weekdays.';
                        if ($teacher->role !== 'teacher' || $teacher->status !== 'active' || ! $state['qualifications']->contains($lesson->subject->name)) $issues[] = $prefix.'teacher is inactive or lacks the approved subject qualification.';
                        if (! $rules->onDuty($teacher, $lesson->day, $lesson->starts_at, $lesson->ends_at)) $issues[] = $prefix.'teacher is outside confirmed availability.';
                        if ($state['breaks']->contains(fn ($b) => $b->weekday === $lesson->day && $rules::overlaps($b->start_time, $b->end_time, $lesson->starts_at, $lesson->ends_at))) $issues[] = $prefix.'overlaps a designated teacher free period.';
                        if ($state['workload']['weekly'] > $state['workload']['limit']) $issues[] = $prefix.'teacher exceeds the weekly workload limit.';
                        if ($state['workload']['subjects']->concat($all->where('teacher_id', $teacher->id)->pluck('subject.name'))->filter()->unique()->count() > 2) $issues[] = $prefix.'teacher exceeds two distinct subjects.';
                    }
                    foreach ($all as $other) {
                        if ($other->id === $lesson->id || $other->day !== $lesson->day || ! $rules::overlaps($other->starts_at, $other->ends_at, $lesson->starts_at, $lesson->ends_at)) continue;
                        foreach (['school_class_id' => 'student cohort', 'teacher_id' => 'teacher', 'classroom_id' => 'physical classroom'] as $key => $label) if ($lesson->$key && $lesson->$key === $other->$key) $issues[] = $prefix.'overlaps lesson #'.$other->id.' for the same '.$label.'.';
                    }
                }
                $issues = array_values(array_unique($issues));
                $cohorts[] = ['cohort_id' => $class->id, 'session' => $class->session, 'status' => $issues ? 'requires_review' : 'valid', 'lesson_count' => $lessons->count(), 'shared_lesson_ids' => $lessons->modelKeys(), 'issues' => $issues, 'subjects' => $subjectRows, 'unscheduled_subjects' => $unscheduled];
            }
            $identityIssues = [];
            if ($matching->count() !== 1) $identityIssues[] = 'Expected exactly one active class identity; found '.$matching->count().'.';
            if (! $cohorts) $identityIssues[] = 'No existing student cohort is linked. Confirm the real cohort and curriculum before generation.';
            $identities[] = ['class' => $grade.$section, 'grade' => $grade, 'section' => $section, 'class_group_ids' => $matching->modelKeys(), 'status' => $identityIssues || collect($cohorts)->contains(fn ($c) => $c['status'] !== 'valid') ? 'requires_review' : 'valid', 'issues' => $identityIssues, 'cohorts' => $cohorts];
        }
        return ['identity_count' => count($identities), 'cohort_count' => array_sum(array_map(fn ($i) => count($i['cohorts']), $identities)), 'valid_identities' => collect($identities)->where('status', 'valid')->count(), 'identities' => $identities];
    }
}
