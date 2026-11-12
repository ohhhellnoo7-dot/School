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
        $settings = \App\Models\AttendanceSessionSetting::where('updated_at', '<=', Carbon::parse($date)->endOfDay())->get()->keyBy('session');
        return Schedule::with('schoolClass')->where('teacher_id', $teacher->id)
            ->where('day', Carbon::parse($date)->format('l'))
            ->where(fn ($q) => $q->whereNull('entry_type')->orWhere('entry_type', Schedule::TYPE_ACADEMIC))
            ->get()->filter(fn ($lesson) => $lesson->schoolClass?->session)
            ->groupBy(fn ($lesson) => $lesson->schoolClass->session)
            ->map(fn ($lessons, $session) => [
                'start' => Carbon::parse($date.' '.($settings->get($session)?->starts_at ?? $lessons->min('starts_at'))),
                'end' => Carbon::parse($date.' '.($settings->get($session)?->ends_at ?? $lessons->max('ends_at'))),
                'grace_minutes' => $settings->get($session)?->grace_minutes ?? 10,
            ]);
    }

    public function scan(User $teacher): array
    {
        $now = now();
        $sessions = $this->sessions($teacher, $now->toDateString());
        // A scan belongs to the ongoing session, or the next session when arriving early.
        $session = $sessions->sortBy('start')->filter(fn ($times) => $times['end']->gte($now))->keys()->first();
        if (! $session) {
            return ['success' => false, 'reason' => 'outside_session', 'message' => 'No remaining scheduled teaching session today. Please check in during your next scheduled session.'];
        }
        $record = TeacherSessionAttendance::firstOrNew([
            'teacher_id' => $teacher->id, 'date' => $now->toDateString(), 'session' => $session,
        ]);
        $fresh = ! $record->scanned_at;
        if ($fresh) {
            $record->fill(['scanned_at' => $now, 'scheduled_start' => $sessions[$session]['start'], 'scheduled_end' => $sessions[$session]['end'],
                'status' => $now->gt($sessions[$session]['start']->copy()->addMinutes($sessions[$session]['grace_minutes'])) ? 'late' : 'present'])->save();
        }
        $this->refresh($teacher, $now->toDateString());
        return ['success' => true, 'status' => $record->status, 'session' => $session, 'fresh' => $fresh, 'session_record_id' => $record->id,
            'encouragement' => $record->status === 'present' && $now->lte($sessions[$session]['start']->copy()->addMinutes($sessions[$session]['grace_minutes']))
                ? $this->sessionMessage($session, false) : null,
            'scanned_at' => $record->scanned_at->toIso8601String(),
            'scheduled_start' => ($record->scheduled_start ?? $sessions[$session]['start'])->toIso8601String(),
            'late_minutes' => $record->status === 'late' ? (int) floor(($record->scheduled_start ?? $sessions[$session]['start'])->diffInMinutes($record->scanned_at, false)) : 0,
            'attendance' => TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', $now)->firstOrFail(),
            'message' => 'Scan successful. '.$session.' attendance: '.$record->status.'.'];
    }

    public function refresh(User $teacher, string $date): void
    {
        DB::transaction(function () use ($teacher, $date) {
            User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            foreach ($this->sessions($teacher, $date) as $session => $times) {
                if (now()->gt($times['start']->copy()->addMinutes($times['grace_minutes']))) {
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

    public function checkout(User $teacher, array $location): array
    {
        $now = now();
        $record = TeacherSessionAttendance::where('teacher_id', $teacher->id)->whereDate('date', $now)
            ->whereNotNull('scanned_at')->orderByDesc('scanned_at')->lockForUpdate()->first();
        if (! $record) {
            return ['success' => false, 'message' => 'Scan Start work first. There is no check-in to finish today.'];
        }
        $end = $record->scheduled_end ?? ($this->sessions($teacher, $now->toDateString())->get($record->session)['end'] ?? null);
        if (! $record->checked_out_at && (! $end || $now->lt($end))) {
            return ['success' => false, 'message' => $end
                ? 'Your '.$record->session.' session finishes at '.$end->format('g:i A').'. Scan Finish work at or after that time.'
                : 'The session end time is unavailable. Ask an administrator to review your timetable.'];
        }
        if (! $record->checked_out_at) {
            $record->fill($location + ['checked_out_at' => $now, 'scheduled_end' => $end])->save();
        }
        return ['success' => true, 'action' => 'check_out', 'status' => $record->status, 'session' => $record->session,
            'encouragement' => $this->sessionMessage($record->session, true),
            'scanned_at' => $record->checked_out_at->toIso8601String(), 'checked_in_at' => $record->scanned_at->toIso8601String(),
            'message' => 'Work finished. Your departure time has been saved.'];
    }

    private function sessionMessage(string $session, bool $finished): string
    {
        if ($finished) {
            return match ($session) {
                'Morning' => 'Thank you for a morning of hard work and care. You made a difference for your students. Enjoy your break!',
                'Afternoon' => 'Thank you for your hard work this afternoon. Your patience and dedication matter. Take a well-earned rest!',
                'Evening' => 'Thank you for giving your time and energy this evening. Your students are lucky to have you. Travel home safely!',
                default => 'Thank you for your hard work and dedication. Take a well-earned rest!',
            };
        }

        return match ($session) {
            'Morning' => 'Good morning! A fresh start and a chance to inspire. Thank you for being here for your students today.',
            'Afternoon' => 'Good afternoon! Bring your energy and kindness into the classroom. Every lesson can make a difference.',
            'Evening' => 'Good evening! Your dedication brightens the classroom. Have a rewarding evening with your students.',
            default => 'Welcome! Thank you for bringing your energy and care to the classroom.',
        };
    }

    public function refreshDate(string $date): void
    {
        if ($date > today()->toDateString()) return;
        User::where('role', 'teacher')->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->each(fn ($teacher) => $this->refresh($teacher, $date));
    }
}
