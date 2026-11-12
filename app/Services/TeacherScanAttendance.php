<?php

namespace App\Services;

use App\Models\{Schedule, TeacherAttendance, TeacherSessionAttendance, User};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherScanAttendance
{
    public function sessions(User $teacher, string $date): Collection
    {
        return Schedule::with('schoolClass')->where('teacher_id', $teacher->id)
            ->where('day', Carbon::parse($date)->format('l'))
            ->where(fn ($q) => $q->whereNull('entry_type')->orWhere('entry_type', Schedule::TYPE_ACADEMIC))
            ->get()->filter(fn ($lesson) => $lesson->schoolClass?->session)
            ->groupBy(fn ($lesson) => $lesson->schoolClass->session)
            ->map(fn ($lessons) => [
                'start' => Carbon::parse($date.' '.$lessons->min('starts_at')),
                'end' => Carbon::parse($date.' '.$lessons->max('ends_at')),
            ]);
    }

    public function scan(User $teacher): array
    {
        $now = now();
        $sessions = $this->sessions($teacher, $now->toDateString());
        // A scan belongs to the ongoing session, or the next session when arriving early.
        $session = $sessions->sortBy('start')->filter(fn ($times) => $times['end']->gte($now))->keys()->first();
        if (! $session) {
            return ['success' => false, 'message' => 'No remaining scheduled teaching session today.'];
        }
        $record = TeacherSessionAttendance::firstOrNew([
            'teacher_id' => $teacher->id, 'date' => $now->toDateString(), 'session' => $session,
        ]);
        $fresh = ! $record->scanned_at;
        if ($fresh) {
            $record->fill(['scanned_at' => $now,
                'status' => $now->gt($sessions[$session]['start']->copy()->addMinutes(10)) ? 'late' : 'present'])->save();
        }
        $this->refresh($teacher, $now->toDateString());
        return ['success' => true, 'status' => $record->status, 'session' => $session, 'fresh' => $fresh, 'session_record_id' => $record->id,
            'scanned_at' => $record->scanned_at->toIso8601String(),
            'scheduled_start' => $sessions[$session]['start']->toIso8601String(),
            'late_minutes' => $record->status === 'late' ? (int) floor($sessions[$session]['start']->diffInMinutes($record->scanned_at, false)) : 0,
            'attendance' => TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', $now)->firstOrFail(),
            'message' => 'Scan successful. '.$session.' attendance: '.$record->status.'.'];
    }

    public function refresh(User $teacher, string $date): void
    {
        DB::transaction(function () use ($teacher, $date) {
            User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            foreach ($this->sessions($teacher, $date) as $session => $times) {
                if (now()->gt($times['start']->copy()->addMinutes(10))) {
                    TeacherSessionAttendance::firstOrCreate([
                        'teacher_id' => $teacher->id, 'date' => $date, 'session' => $session,
                    ], ['status' => 'absent']);
                }
            }
            $records = TeacherSessionAttendance::where('teacher_id', $teacher->id)->whereDate('date', $date)->get();
            if ($records->isEmpty()) return;
            $existing = TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', $date)->first();
            // Explicit administrator marks remain authoritative in the daily summary.
            if ($existing && $existing->recorded_by !== $teacher->id) return;
            $status = $records->contains('status', 'absent') ? 'absent'
                : ($records->contains('status', 'late') ? 'late' : 'present');
            $daily = $existing ?? new TeacherAttendance(['teacher_id' => $teacher->id, 'date' => $date]);
            $daily->fill([
                'program' => $teacher->program, 'status' => $status, 'recorded_by' => $teacher->id,
            ])->save();
        });
    }

    public function refreshDate(string $date): void
    {
        if ($date > today()->toDateString()) return;
        User::where('role', 'teacher')->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->each(fn ($teacher) => $this->refresh($teacher, $date));
    }
}
