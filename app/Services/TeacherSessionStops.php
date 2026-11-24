<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, EmployeeSalaryProfile, Schedule, SchoolClass, Subject, TeacherSessionStop, User};
use App\Support\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherSessionStops
{
    public function sessions(User $teacher): Collection
    {
        return collect(AttendanceSession::OPTIONS)->filter(fn ($session) =>
            AttendanceSession::teachers(User::whereKey($teacher->id), $session)->exists()
            || ClassroomBooking::where('teacher_id', $teacher->id)->whereHas('classSession', fn ($class) => $class->where('session', $session))->exists()
        );
    }

    public function pending(User $teacher): Collection
    {
        return TeacherSessionStop::where('teacher_id', $teacher->id)->whereNull('applied_at')->get()->keyBy('session');
    }

    public function schedule(User $teacher, string $session): void
    {
        app(TeacherManagementService::class)->locked(function () use ($teacher, $session) {
            if (!$this->sessions($teacher)->contains($session)) {
                throw ValidationException::withMessages(['session' => __('Choose a currently assigned session.')]);
            }
            TeacherSessionStop::firstOrCreate([
                'teacher_id' => $teacher->id,
                'session' => $session,
                'effective_on' => today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ]);
        });
    }

    public function cancel(User $teacher, string $session): bool
    {
        return app(TeacherManagementService::class)->locked(fn () => TeacherSessionStop::where('teacher_id', $teacher->id)
            ->where('session', $session)->whereNull('applied_at')->whereDate('effective_on', '>', today())->delete() > 0);
    }

    public function applyDue(): void
    {
        $due = TeacherSessionStop::whereNull('applied_at')->whereDate('effective_on', '<=', today());
        if (!$due->exists()) {
            return;
        }

        app(TeacherManagementService::class)->locked(function () use ($due) {
            $groups = $due->orderBy('effective_on')->orderBy('id')->get()
                ->groupBy(fn ($stop) => $stop->teacher_id.'|'.$stop->effective_on->toDateString());
            foreach ($groups as $stops) {
                $this->apply($stops);
            }
        });
    }

    private function apply(Collection $stops): void
    {
        $first = $stops->first();
        $effective = $first->effective_on;
        $teacher = User::withoutGlobalScope('archived')->lockForUpdate()->findOrFail($first->teacher_id);
        $sessions = $stops->pluck('session');
        $profile = EmployeeSalaryProfile::where('teacher_id', $teacher->id)->lockForUpdate()->first();
        $previousMonth = $effective->copy()->subMonth();
        $minutes = app(EmployeeSalaryCalculator::class)->assignedMinutes([$teacher->id])->get($teacher->id, 0);
        $report = app(TeacherLeaveReport::class)->forTeacher($teacher, $previousMonth, $profile);
        $snapshot = [
            'sessions' => $sessions->all(),
            'profile' => $profile?->only(['teacher_id', 'day_sessions', 'evening_sessions', 'service_started_on']),
            'requiredBonusMonths' => app(EmployeeAttendanceBonus::class)->requiredServiceMonths(),
            'minutes' => $minutes,
            'leaveMonths' => $report['leaveMonths']->all(),
            'attendanceBonus' => $report['attendanceBonus'],
            'salary' => $report['salary'],
        ];

        $classes = SchoolClass::withoutGlobalScope('unmerged')->whereIn('session', $sessions)->select('id');
        $lessons = Schedule::where('teacher_id', $teacher->id)->whereIn('school_class_id', clone $classes);
        $subjects = Subject::where('teacher_id', $teacher->id)->whereIn('school_class_id', clone $classes);
        $assignments = SchoolClass::withoutGlobalScope('unmerged')->where('teacher_id', $teacher->id)->whereIn('session', $sessions);
        $bookings = ClassroomBooking::where('teacher_id', $teacher->id)
            ->whereHas('classSession', fn ($class) => $class->whereIn('session', $sessions));
        $groups = ClassGroup::where('teacher_id', $teacher->id)
            ->whereHas('sessions', fn ($class) => $class->whereIn('session', $sessions))
            ->whereDoesntHave('sessions', fn ($class) => $class->whereNotIn('session', $sessions));
        foreach (['lessons' => $lessons, 'subjects' => $subjects, 'classes' => $assignments, 'bookings' => $bookings, 'groups' => $groups] as $key => $query) {
            $snapshot['assignments'][$key] = (clone $query)->pluck($query->getModel()->qualifyColumn('id'))->all();
        }
        $lessons->update(['cancelled_at' => $effective]);
        foreach ([$subjects, $assignments, $bookings, $groups] as $query) {
            $query->update(['teacher_id' => null]);
        }

        foreach ($sessions as $session) {
            [$start, $end] = TeacherManagementService::SESSIONS[$session];
            $hours = config('classrooms.program_session_hours.'.$teacher->program.'.'.$session);
            DB::table('teacher_working_periods')->where('teacher_id', $teacher->id)
                ->where('start_time', '>=', $hours['starts_at'] ?? $start)
                ->where('end_time', '<=', $hours['ends_at'] ?? $end)->delete();
        }

        if ($profile) {
            $profile->day_sessions = max(0, $profile->day_sessions - $sessions->intersect(['Morning', 'Afternoon'])->count());
            $profile->evening_sessions = $sessions->contains('Evening') ? 0 : $profile->evening_sessions;
            $profile->save();
        }

        $first->snapshot = $snapshot;
        $first->save();
        TeacherSessionStop::whereKey($stops->modelKeys())->update(['applied_at' => now()]);
    }

    public function history(array $teacherIds): Collection
    {
        return TeacherSessionStop::whereIn('teacher_id', $teacherIds)->whereNotNull('snapshot')
            ->whereNotNull('applied_at')->orderBy('effective_on')->get()->groupBy('teacher_id');
    }

    public function payrollAt(Collection $history, Carbon $month, ?EmployeeSalaryProfile $profile, int $minutes): array
    {
        $stop = $history->first(fn ($stop) => $stop->effective_on->gt($month));
        if (!$stop) {
            return [$profile, $minutes];
        }

        return [
            $stop->snapshot['profile'] ? new EmployeeSalaryProfile($stop->snapshot['profile']) : null,
            $stop->snapshot['minutes'],
        ];
    }
}
