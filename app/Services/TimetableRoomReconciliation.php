<?php

namespace App\Services;

use App\Models\{Classroom, ClassroomBooking, Schedule};
use Illuminate\Support\Facades\DB;

class TimetableRoomReconciliation
{
    public function describe(Schedule $lesson): array
    {
        $confirmed = $lesson->classroom_id && Classroom::whereKey($lesson->classroom_id)->exists();
        $proposals = ClassroomBooking::where('class_group_id', $lesson->schoolClass?->class_group_id)
            ->whereHas('classSession', fn ($q) => $q->where('session', $lesson->schoolClass?->session))
            ->where('day_of_week', $lesson->day)->where('starts_at', '<=', $lesson->starts_at)->where('ends_at', '>=', $lesson->ends_at)
            ->pluck('classroom_id')->unique();
        $proposed = $proposals->count() === 1 ? $proposals->first() : null;
        return ['lesson_id' => $lesson->id, 'confirmed' => (bool) $confirmed, 'proposed_id' => $proposed, 'reason' => $confirmed ? 'Physical room ID is confirmed.' : ($proposed ? 'A reservation proposes this room but does not establish the recorded lesson assignment. Administrator confirmation required.' : 'No unambiguous reservation. A matching room label does not establish a physical room assignment.')];
    }

    public function scan(): array
    {
        $lessons = Schedule::withoutGlobalScope('active')->with('schoolClass')->get();
        $verified = $manual = [];
        foreach ($lessons as $lesson) {
            $description = $this->describe($lesson);
            if ($description['confirmed']) continue;
            $room = $description['proposed_id'] ? Classroom::find($description['proposed_id']) : null;
            if ($room && in_array(RoomLessonService::roomKey($lesson->room), [RoomLessonService::roomKey($room->room_number), RoomLessonService::roomKey($room->name)], true)) $verified[] = $description;
            else $manual[] = $description;
        }
        $conflicts = [];
        foreach ($lessons as $index => $lesson) foreach ($lessons->slice($index + 1) as $other) {
            if ($lesson->cancelled_at || $other->cancelled_at || $lesson->day !== $other->day || ! TeacherManagementService::overlaps($lesson->starts_at, $lesson->ends_at, $other->starts_at, $other->ends_at)) continue;
            foreach (['teacher_id', 'school_class_id', 'classroom_id'] as $field) if ($lesson->$field && $lesson->$field === $other->$field) $conflicts[] = ['resource' => $field, 'lessons' => [$lesson->id, $other->id]];
        }
        return ['total_classrooms' => Classroom::count(), 'total_lessons' => $lessons->count(), 'affected_lessons' => count($verified) + count($manual), 'affected_classrooms' => collect([...$verified, ...$manual])->pluck('proposed_id')->filter()->unique()->count(), 'verified' => $verified, 'manual_review' => $manual, 'conflicts' => $conflicts];
    }

    public function globalPreview(): array
    {
        $scan = $this->scan();
        return $this->preview(collect($scan['verified'])->mapWithKeys(fn ($r) => [$r['lesson_id'] => $r['proposed_id']])->all());
    }

    public function preview(array $assignments, array $roomChangeReasons = []): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($assignments, $roomChangeReasons) {
            $rows = [];
            foreach ($assignments as $id => $roomId) {
                $lesson = Schedule::withoutGlobalScope('active')->findOrFail($id);
                $room = Classroom::findOrFail($roomId);
                if ($room->status !== 'active') app(TeacherManagementService::class)->fail('Choose an active physical classroom.');
                $rows[] = ['schedule_id' => $lesson->id, 'previous_room' => $lesson->room, 'previous_classroom_id' => $lesson->classroom_id, 'classroom_id' => $room->id, 'room' => $room->room_number];
                if (! empty($roomChangeReasons[$id])) {
                    if (! is_string($roomChangeReasons[$id]) || mb_strlen($roomChangeReasons[$id]) > 500) app(TeacherManagementService::class)->fail('Room change reasons must be at most 500 characters.');
                    $rows[array_key_last($rows)]['room_change_reason'] = $roomChangeReasons[$id];
                }
            }
            $this->validateRows($rows);
            return ['rows' => $rows, 'fingerprint' => app(RotationTimetableGenerator::class)->fingerprint()];
        });
    }

    private function validateRows(array $rows): void
    {
        $lessons = Schedule::withoutGlobalScope('active')->get();
        foreach ($rows as $row) if ($lesson = $lessons->firstWhere('id', $row['schedule_id'])) {
            $lesson->classroom_id = $row['classroom_id'];
            if (isset($row['room_change_reason'])) $lesson->room_change_reason = $row['room_change_reason'];
        }
        $validator = app(SchoolRoomAssignmentRepair::class);
        foreach ($rows as $row) {
            $lesson = $lessons->firstWhere('id', $row['schedule_id']);
            if ($lesson->cancelled_at) continue;
            $issues = $validator->candidateIssues($lesson,$row['classroom_id'],array_column($rows,'schedule_id'));
            if ($issues) app(TeacherManagementService::class)->fail(implode(' ',$issues));
            if ($lessons->contains(fn ($other) => $other->id !== $lesson->id && ! $other->cancelled_at && $other->classroom_id === $lesson->classroom_id && $other->day === $lesson->day && \App\Support\AcademicYear::overlaps($other->academic_year,$lesson->academic_year) && TeacherManagementService::overlaps($other->starts_at, $other->ends_at, $lesson->starts_at, $lesson->ends_at))) app(TeacherManagementService::class)->fail('Room correction would create a physical classroom overlap. Resolve the conflict first.');
        }
    }

    public function confirm(array $plan, int $administratorId): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($plan, $administratorId) {
            if (! hash_equals($plan['fingerprint'], app(RotationTimetableGenerator::class)->fingerprint())) app(TeacherManagementService::class)->fail('Scheduling data changed. Review room assignments again.');
            $this->validateRows($plan['rows']);
            $backup = app(TimetableBackup::class)->create();
            foreach ($plan['rows'] as $row) {
                DB::table('lesson_room_corrections')->insert(['schedule_id' => $row['schedule_id'], 'previous_room' => $row['previous_room'], 'previous_classroom_id' => $row['previous_classroom_id'], 'classroom_id' => $row['classroom_id'], 'corrected_by' => $administratorId, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('schedules')->where('id', $row['schedule_id'])->update(['classroom_id' => $row['classroom_id'], 'room' => $row['room'], 'updated_at' => now()] + (isset($row['room_change_reason']) ? ['room_change_reason'=>$row['room_change_reason']] : []));
            }
            return ['repaired' => count($plan['rows']), 'backup' => $backup, 'after' => $this->scan()];
        });
    }
}
