<?php

namespace App\Services;

use App\Models\{Classroom, ClassroomBooking, Schedule};
use App\Support\AcademicYear;
use Illuminate\Support\Collection;

/** Resolves legacy physical-room references from explicit records, never class names. */
class SchoolRoomAssignmentRepair
{
    private ?Collection $rooms = null;
    private ?Collection $bookings = null;
    private ?Collection $lessons = null;
    private ?Collection $candidateIds = null;

    private function load(): void
    {
        $this->rooms ??= Classroom::all();
        $this->bookings ??= ClassroomBooking::with('classSession')->get();
        $this->lessons ??= Schedule::with(['schoolClass', 'subject', 'teacher'])->get();
        if ($this->candidateIds === null) $this->candidateIds = $this->lessons->mapWithKeys(function ($lesson) {
            if ($lesson->classroom_id && $this->rooms->contains('id',$lesson->classroom_id)) return [$lesson->id=>$lesson->classroom_id];
            $key = RoomLessonService::roomKey($lesson->room);
            $matches = $key === '' ? collect() : $this->rooms->filter(fn ($room)=>in_array($key,[RoomLessonService::roomKey($room->room_number),RoomLessonService::roomKey($room->name)],true));
            if ($matches->count() > 1) return [$lesson->id=>null];
            if ($matches->count() === 1) return [$lesson->id=>$matches->first()->id];
            $rooms = $this->bookings->filter(fn ($b)=>$lesson->schoolClass?->class_group_id && $b->class_group_id === $lesson->schoolClass->class_group_id && $b->classSession?->session === $lesson->schoolClass->session && $b->day_of_week === $lesson->day && TeacherManagementService::overlaps($b->starts_at,$b->ends_at,$lesson->starts_at,$lesson->ends_at))->pluck('classroom_id')->unique();
            return [$lesson->id=>$rooms->count() === 1 ? $rooms->first() : null];
        });
    }

    public function describe(Schedule $lesson): array
    {
        $this->load();
        $class = $lesson->schoolClass;
        $key = RoomLessonService::roomKey($lesson->room);
        $matches = $key === '' ? collect() : $this->rooms->filter(fn ($room) => in_array($key, [RoomLessonService::roomKey($room->room_number), RoomLessonService::roomKey($room->name)], true));
        $own = $this->bookings->filter(fn ($b) => $class && $class->class_group_id && $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->day_of_week === $lesson->day && TeacherManagementService::overlaps($b->starts_at, $b->ends_at, $lesson->starts_at, $lesson->ends_at));
        $covering = $own->filter(fn ($b) => TeacherManagementService::minute($b->starts_at) <= TeacherManagementService::minute($lesson->starts_at) && TeacherManagementService::minute($b->ends_at) >= TeacherManagementService::minute($lesson->ends_at))->pluck('classroom_id')->unique();
        $physical = $this->rooms->firstWhere('id', $lesson->classroom_id);
        $root = $physical ? 'valid_reference' : ($lesson->classroom_id ? 'invalid_classroom_id' : 'missing_classroom_id');
        $issues = [];
        $candidate = $physical;
        if (! $physical) {
            if ($matches->count() > 1) $issues[] = 'Recorded room name is ambiguous.';
            elseif ($matches->count() === 1) $candidate = $matches->first();
            elseif ($covering->count() === 1) $candidate = $this->rooms->firstWhere('id', $covering->first());
            elseif ($own->pluck('classroom_id')->unique()->count() === 1) $candidate = $this->rooms->firstWhere('id',$own->first()->classroom_id);
            else $issues[] = $key === '' ? 'Classroom assignment is missing.' : 'Physical classroom not found; no unique explicit reservation resolves the recorded room.';
        }
        if ($candidate) {
            $issues = array_merge($issues, $this->candidateIssues($lesson, $candidate->id));
            if ($covering->count() > 1) $issues[] = 'Multiple physical room reservations cover this cohort and lesson.';
        }
        $labelMismatch = $physical && ! in_array($key, [RoomLessonService::roomKey($physical->room_number), RoomLessonService::roomKey($physical->name)], true);
        $needsRepair = ! $physical || $labelMismatch;
        if ($labelMismatch) $root = 'legacy_label_mismatch';
        $evidence = $physical ? 'Existing physical classroom ID is authoritative.' : ($matches->count() === 1 ? 'Unique existing physical room number/name.' : ($candidate ? ($covering->count() === 1 ? 'Unique covering reservation linked by class identity ID and cohort session.' : 'An overlapping reservation identifies a possible room but does not cover the full lesson.') : 'No unique assignment evidence.'));
        return ['lesson_id' => $lesson->id, 'program' => $class?->program, 'class' => $class?->label, 'cohort_id' => $lesson->school_class_id, 'weekday' => $lesson->day, 'starts_at' => $lesson->starts_at, 'ends_at' => $lesson->ends_at, 'academic_year' => $lesson->academic_year, 'teacher' => $lesson->teacher?->name, 'recorded_room' => $lesson->room, 'classroom_id' => $lesson->classroom_id, 'proposed_id' => $candidate?->id, 'physical_room' => $candidate?->room_number, 'root_cause' => $root, 'needs_repair' => $needsRepair, 'repairable' => $needsRepair && $candidate && ! $issues, 'issues' => array_values(array_unique($issues)), 'evidence' => $evidence, 'reason' => $issues ? implode(' ',array_unique($issues)) : ($needsRepair ? $evidence : 'Physical classroom assignment is valid.')];
    }

    public function candidateIssues(Schedule $lesson, int $roomId, array $ignoreLessons = []): array
    {
        $this->load();
        $room = $this->rooms->firstWhere('id',$roomId);
        if (! $room) return ['Physical classroom not found.'];
        $issues = [];
        if (! $lesson->cancelled_at && $room->status !== 'active') $issues[] = 'Physical classroom is out of service.';
        if (! $lesson->cancelled_at && $lesson->schoolClass && $room->capacity < $lesson->schoolClass->students()->where('status','active')->count()) $issues[] = 'Physical classroom capacity is insufficient.';
        if ($lesson->cancelled_at) return $issues;
        $class = $lesson->schoolClass;
        if ($this->bookings->contains(fn ($b) => $b->classroom_id === $roomId && (! $class || $b->class_group_id !== $class->class_group_id || $b->classSession?->session !== $class->session) && $b->day_of_week === $lesson->day && TeacherManagementService::overlaps($b->starts_at,$b->ends_at,$lesson->starts_at,$lesson->ends_at))) $issues[] = 'Room is already reserved for another student cohort.';
        if ($this->lessons->contains(fn ($other) => $other->id !== $lesson->id && ! in_array($other->id,$ignoreLessons,true) && $this->candidateIds->get($other->id) === $roomId && $other->day === $lesson->day && AcademicYear::overlaps($other->academic_year,$lesson->academic_year) && TeacherManagementService::overlaps($other->starts_at,$other->ends_at,$lesson->starts_at,$lesson->ends_at))) $issues[] = 'Lesson conflicts with another class in this physical classroom.';
        $own = $this->bookings->filter(fn ($b) => $class && $class->class_group_id && $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->day_of_week === $lesson->day && TeacherManagementService::overlaps($b->starts_at,$b->ends_at,$lesson->starts_at,$lesson->ends_at));
        if ($own->contains('classroom_id',$roomId) && ! $own->contains(fn ($b) => $b->classroom_id === $roomId && TeacherManagementService::minute($b->starts_at) <= TeacherManagementService::minute($lesson->starts_at) && TeacherManagementService::minute($b->ends_at) >= TeacherManagementService::minute($lesson->ends_at))) $issues[] = 'Lesson extends outside its reserved session times. Review the lesson time before assigning this room.';
        elseif ($own->isNotEmpty() && ! $own->contains('classroom_id',$roomId) && ! $lesson->room_change_reason) $issues[] = 'Classroom assignment differs from its reservation; an approved room change is required.';
        return array_values(array_unique($issues));
    }

    public function scan(): array
    {
        $this->rooms = $this->bookings = $this->lessons = $this->candidateIds = null;
        $this->load();
        $records = Schedule::withoutGlobalScope('active')->with(['schoolClass','teacher'])->orderBy('id')->get();
        $descriptions = $records->mapWithKeys(fn ($lesson)=>[$lesson->id=>$this->describe($lesson)]);
        // Compare all proposed assignments together, not only already confirmed FKs.
        $conflicts = [];
        $active = $records->filter(fn ($s)=>! $s->cancelled_at)->values();
        foreach ($active as $index=>$lesson) foreach ($active->slice($index+1) as $other) {
            if ($lesson->day !== $other->day || ! AcademicYear::overlaps($lesson->academic_year,$other->academic_year) || ! TeacherManagementService::overlaps($lesson->starts_at,$lesson->ends_at,$other->starts_at,$other->ends_at)) continue;
            $resources = [];
            if ($lesson->teacher_id && $lesson->teacher_id === $other->teacher_id) $resources[] = 'teacher';
            if ($lesson->school_class_id && $lesson->school_class_id === $other->school_class_id) $resources[] = 'student_cohort';
            $a = $descriptions[$lesson->id]; $b = $descriptions[$other->id];
            if ($a['proposed_id'] && $a['proposed_id'] === $b['proposed_id']) {
                $resources[] = 'classroom';
                foreach ([$lesson,$other] as $record) {
                    $description = $descriptions[$record->id];
                    $description['repairable'] = false;
                    $description['issues'][] = 'Proposed physical room conflicts with lesson #'.($record->id === $lesson->id ? $other->id : $lesson->id).'.';
                    $description['reason'] = implode(' ',$description['issues']);
                    $descriptions[$record->id] = $description;
                }
            }
            foreach ($resources as $resource) $conflicts[] = ['resource'=>$resource,'lessons'=>[$lesson->id,$other->id],'details'=>[$descriptions[$lesson->id],$descriptions[$other->id]]];
        }
        $affected = $descriptions->filter(fn ($r)=>$r['needs_repair'] || $r['issues']);
        return ['total_classrooms'=>$this->rooms->count(),'total_classes'=>\App\Models\ClassGroup::count(),'total_cohorts'=>\App\Models\SchoolClass::count(),'total_lessons'=>$records->count(),'active_lessons'=>$active->count(),'affected_lessons'=>$affected->count(),'affected_classrooms'=>$affected->pluck('proposed_id')->filter()->unique()->count(),'verified'=>$affected->where('repairable',true)->values()->all(),'manual_review'=>$affected->where('repairable',false)->values()->all(),'conflicts'=>$conflicts,'records'=>$descriptions->values()->all()];
    }

    public function repairUnambiguous(int $administratorId): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($administratorId) {
            $before = $this->scan();
            $assignments = collect($before['verified'])->mapWithKeys(fn ($r)=>[$r['lesson_id']=>$r['proposed_id']])->all();
            $manual = app(TimetableRoomReconciliation::class);
            $plan = $manual->preview($assignments);
            $result = $manual->confirm($plan,$administratorId);
            return ['before'=>$before,'repaired'=>$result['repaired'],'backup'=>$result['backup'],'after'=>$this->scan()];
        });
    }
}
