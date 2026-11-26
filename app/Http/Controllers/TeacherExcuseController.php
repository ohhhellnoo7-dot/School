<?php

namespace App\Http\Controllers;

use App\Models\TeacherAttendance;
use App\Models\TeacherExcuse;
use App\Models\User;
use App\Services\SchoolHolidayClosure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherExcuseController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->role === 'administrator';
        $excuses = TeacherExcuse::with('teacher:id,name')
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')->paginate(25);

        return view('school.attendance.teacher-excuses', compact('excuses', 'isAdmin'));
    }

    public function count()
    {
        return response()->json(['count' => TeacherExcuse::pending()->count()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, SchoolHolidayClosure $holidays)
    {
        $data = $request->validateWithBag('teacherExcuse', [
            'starts_on' => 'required|date_format:Y-m-d|after_or_equal:today',
            'days' => 'required|integer|between:1,365',
            'reason' => 'required|string|max:1000',
        ]);
        $start = CarbonImmutable::parse($data['starts_on']);
        $end = $start->addDays($data['days'] - 1);
        $teacherId = $request->user()->id;
        $this->attendanceDates($start, $end, $holidays);

        DB::transaction(function () use ($teacherId, $start, $end, $data) {
            User::whereKey($teacherId)->lockForUpdate()->firstOrFail();
            if (TeacherExcuse::where('teacher_id', $teacherId)->whereIn('status', ['pending', 'approved'])
                ->overlapping($start->toDateString(), $end->toDateString())->exists()) {
                throw ValidationException::withMessages([
                    'starts_on' => __('You already have a pending or approved request for these dates.'),
                ])->errorBag('teacherExcuse');
            }

            TeacherExcuse::create([
                'teacher_id' => $teacherId,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'reason' => $data['reason'],
            ]);
        }, 3);

        return redirect()->route('dashboard')->with('success', __('Excuse request submitted for review.'));
    }

    public function review(Request $request, TeacherExcuse $excuse, SchoolHolidayClosure $holidays)
    {
        $status = $request->validate(['status' => 'required|in:approved,rejected'])['status'];

        DB::transaction(function () use ($request, $excuse, $status, $holidays) {
            $teacher = User::whereKey($excuse->teacher_id)->lockForUpdate()->firstOrFail();
            $excuse = TeacherExcuse::lockForUpdate()->findOrFail($excuse->id);
            if ($excuse->status !== 'pending') {
                throw ValidationException::withMessages(['status' => __('This request has already been reviewed.')]);
            }

            if ($status === 'approved') {
                foreach ($this->attendanceDates($excuse->starts_on, $excuse->ends_on, $holidays) as $date) {
                    $attendance = TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', $date)->first()
                        ?? new TeacherAttendance(['teacher_id' => $teacher->id, 'date' => $date]);
                    $attendance->fill([
                        'program' => $attendance->program ?? $teacher->program,
                        'status' => 'excused',
                        'notes' => $excuse->reason,
                        'recorded_by' => $request->user()->id,
                    ])->save();
                }
            }

            $excuse->update(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }, 3);

        return redirect()->route('teacher-excuses.index')->with('success', __('Excuse request reviewed.'));
    }

    private function attendanceDates(CarbonImmutable $start, CarbonImmutable $end, SchoolHolidayClosure $holidays): array
    {
        $closedDates = $holidays->enabled() ? array_flip($holidays->calendarDates()) : [];
        $dates = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $date = $day->toDateString();
            if (! isset($closedDates[$date])) {
                $dates[] = $date;
            }
        }

        if ($dates === []) {
            throw ValidationException::withMessages([
                'starts_on' => __('Choose a range that includes a day when the school is open.'),
            ])->errorBag('teacherExcuse');
        }

        return $dates;
    }
}
