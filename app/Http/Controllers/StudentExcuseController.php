<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassAttendance;
use App\Models\StudentExcuse;
use App\Models\User;
use App\Services\SchoolHolidayClosure;
use App\Support\AttendanceSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentExcuseController extends Controller
{
    public function store(Request $request, SchoolHolidayClosure $holidays)
    {
        $guardian = $request->user();
        abort_unless(in_array($guardian->role, User::GUARDIAN_ROLES, true) && $guardian->children()->exists(), 403);
        $data = $request->validateWithBag('excuse', [
            'child_id' => 'required|integer|min:1',
            'session' => ['required', Rule::in(AttendanceSession::OPTIONS)],
            'starts_on' => 'required|date_format:Y-m-d|after_or_equal:today',
            'days' => 'required|integer|between:1,365',
            'reason' => 'required|string|max:2000',
        ]);
        $start = CarbonImmutable::parse($data['starts_on']);
        $end = $start->addDays($data['days'] - 1);
        $dates = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            if (! $day->isWeekend() && ! $holidays->holiday($day)) {
                $dates[] = $day->toDateString();
            }
        }

        DB::transaction(function () use ($guardian, $data, $start, $end, $dates) {
            $child = $guardian->children()->lockForUpdate()->findOrFail($data['child_id']);
            $classes = $child->studyClasses()->where('session', $data['session'])->get();
            if ($classes->isEmpty() || $dates === []) {
                throw ValidationException::withMessages($classes->isEmpty()
                    ? ['session' => __('Choose one of this child’s study sessions.')]
                    : ['starts_on' => __('Choose a date range that includes a school day.')])->errorBag('excuse');
            }

            $excuse = StudentExcuse::firstOrCreate([
                'student_id' => $child->id, 'session' => $data['session'],
                'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
            ], ['requested_by' => $guardian->id, 'reason' => $data['reason']]);
            if (! $excuse->wasRecentlyCreated) {
                return;
            }

            foreach ($dates as $date) {
                $daily = Attendance::where('student_id', $child->id)->whereDate('date', $date)->first()
                    ?? new Attendance(['student_id' => $child->id, 'date' => $date]);
                $daily->fill(['status' => 'excused'])->save();
                foreach ($classes as $class) {
                    ClassAttendance::updateOrCreate(
                        ['student_id' => $child->id, 'school_class_id' => $class->id, 'date' => $date],
                        ['status' => 'excused', 'recorded_by' => $guardian->id],
                    );
                }
            }
        }, 3);

        return redirect()->route('students.index', ['my_children' => 1])
            ->with('success', __('Excuse submitted. Attendance has been marked excused for the selected session.'));
    }

    public function count(Request $request)
    {
        $count = StudentExcuse::submittedToday()->where('id', '>', $request->user()->last_seen_student_excuse_id ?? 0)->count();

        return response()->json(['count' => $count])->header('Cache-Control', 'private, no-store');
    }

    public function markSeen(Request $request)
    {
        $through = $request->validate(['through' => 'required|integer|min:1'])['through'];
        if (StudentExcuse::submittedToday()->whereKey($through)->exists()) {
            User::whereKey($request->user()->id)->where('last_seen_student_excuse_id', '<', $through)
                ->update(['last_seen_student_excuse_id' => $through]);
        }

        return response()->noContent();
    }

    public function index(Request $request, SchoolHolidayClosure $holidays)
    {
        $date = $request->validate(['date' => 'required|date_format:Y-m-d'])['date'];
        $through = StudentExcuse::submittedToday()->where('id', '>', $request->user()->last_seen_student_excuse_id ?? 0)->max('id');
        $excuses = CarbonImmutable::parse($date)->isWeekend() || $holidays->holiday($date)
            ? collect()
            : StudentExcuse::overlapping($date, $date)->with('student:id,name,student_number')
                ->latest('id')->get();

        return response()->view('school.students.excuse-results', compact('excuses'))
            ->header('Cache-Control', 'private, no-store')->header('X-Excuse-Notifications-Through', (string) ($through ?? 0));
    }
}
