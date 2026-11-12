<?php

namespace App\Http\Controllers;

use App\Models\SchoolLocation;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolMapController extends Controller
{
    public function scanState(Request $request)
    {
        return response()->json(app(\App\Services\TeacherScanAttendance::class)->scanState($request->user()))
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['location' => 'nullable|string|max:300', 'site' => 'nullable|integer']);
        $savedLocations = SchoolLocation::orderBy('name')->get();
        $locations = $savedLocations->reject(fn ($site) => $site->isLegacyTest())->values();
        $activeLocation = $locations->firstWhere('is_active', true);
        $selectedLocation = $locations->firstWhere('id', $data['site'] ?? null) ?? $activeLocation ?? $locations->first();
        $location = trim($data['location'] ?? ($selectedLocation ? $selectedLocation->latitude.','.$selectedLocation->longitude : config('services.school_map.location', '')));

        if ($request->user()->role === 'teacher') {
            $selectedLocation = $activeLocation;
            $location = $activeLocation ? $activeLocation->latitude.','.$activeLocation->longitude : '';
            $locations = collect($activeLocation ? [$activeLocation] : []);
            $savedLocations = collect();
        }

        return view('school.map', compact('location', 'locations', 'selectedLocation', 'savedLocations', 'activeLocation'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (strtolower(trim($value)) === SchoolLocation::LEGACY_TEST_NAME) {
                    $fail('Test locations cannot be used for attendance. Save the actual school coordinates.');
                }
            }],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|between:20,5000',
        ]);
        $location = DB::transaction(function () use ($request, $data) {
            SchoolLocation::orderBy('id')->lockForUpdate()->get();
            SchoolLocation::where('is_active', true)->update(['is_active' => null]);
            return SchoolLocation::create($data + ['created_by' => $request->user()->id, 'is_active' => true]);
        }, 3);

        return redirect()->route('school.map', ['site' => $location->id])->with('success', 'Attendance location saved and activated. Previous locations are history only.');
    }

    public function setFromMap(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate(['location' => ['required', 'string', 'max:300',
            'regex:/^\s*[+-]?(?:\d+(?:\.\d*)?|\.\d+)\s*,\s*[+-]?(?:\d+(?:\.\d*)?|\.\d+)\s*$/']]);
        [$latitude, $longitude] = array_map('trim', explode(',', $data['location']));
        validator(['latitude' => $latitude, 'longitude' => $longitude], [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ])->validate();
        $location = DB::transaction(function () use ($request, $latitude, $longitude) {
            SchoolLocation::orderBy('id')->lockForUpdate()->get();
            $target = SchoolLocation::attendanceEligible()->where('latitude', $latitude)
                ->where('longitude', $longitude)->orderByDesc('id')->first();
            SchoolLocation::where('is_active', true)->update(['is_active' => null]);
            if ($target) {
                $target->update(['is_active' => true]);
                return $target;
            }
            return SchoolLocation::create(['name' => 'Attendance area '.$latitude.','.$longitude,
                'latitude' => $latitude, 'longitude' => $longitude, 'radius_meters' => 100,
                'created_by' => $request->user()->id, 'is_active' => true]);
        }, 3);

        return redirect()->route('school.map', ['site' => $location->id])
            ->with('success', 'Attendance location set. Scans now use these coordinates only.');
    }

    public function activate(Request $request, SchoolLocation $location)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        DB::transaction(function () use ($location) {
            SchoolLocation::orderBy('id')->lockForUpdate()->get();
            $target = SchoolLocation::attendanceEligible()->whereKey($location->id)->firstOrFail();
            SchoolLocation::where('is_active', true)->update(['is_active' => null]);
            $target->update(['is_active' => true]);
        }, 3);

        return redirect()->route('school.map')->with('success', 'Active attendance location changed. Start a new scan.');
    }

    public function updateRadius(Request $request, SchoolLocation $location)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate(['radius_meters' => 'required|integer|between:20,5000']);
        DB::transaction(function () use ($location, $data) {
            $target = SchoolLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
            $target->update($data);
        });

        return redirect()->route('school.map', ['site' => $location->id])
            ->with('success', 'Attendance radius updated. Start a new scan to use the new radius.');
    }

    public function destroy(Request $request, SchoolLocation $location)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        DB::transaction(function () use ($location) {
            $target = SchoolLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
            $target->update(['is_active' => null]);
            $target->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Location removed from attendance scanning.']);
        }

        return redirect()->route('school.map')->with('success', 'Location removed from attendance scanning.');
    }

    public function scan(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher'], true), 403);
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:100000',
            'action' => 'nullable|in:check_in,check_out',
            'expected_location_id' => 'nullable|integer',
            'target_location' => 'nullable|string|max:300',
            'sampled_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(2)->toIso8601String(), 'before_or_equal:'.now()->addSeconds(30)->toIso8601String()],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $location = SchoolLocation::attendanceEligible()->where('is_active', true)->lockForUpdate()->firstOrFail();
            if (isset($data['expected_location_id']) && (int) $data['expected_location_id'] !== $location->id) {
                return response()->json(['success' => false, 'message' => 'The active attendance location changed. Refresh the page and scan again.'], 409);
            }
            if ($request->user()->role === 'administrator' && isset($data['target_location'])) {
                $parts = explode(',', $data['target_location']);
                if (count($parts) !== 2 || !is_numeric(trim($parts[0])) || !is_numeric(trim($parts[1]))
                    || abs((float) $parts[0] - (float) $location->latitude) > 0.00000005
                    || abs((float) $parts[1] - (float) $location->longitude) > 0.00000005) {
                    return response()->json(['success' => false, 'message' => 'The coordinates in the box are not the active attendance location. Click Set attendance location before scanning.'], 409);
                }
            }
            $lat1 = deg2rad((float) $location->latitude);
            $lat2 = deg2rad((float) $data['latitude']);
            $deltaLat = $lat2 - $lat1;
            $deltaLng = deg2rad((float) $data['longitude'] - (float) $location->longitude);
            $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
            $distance = 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
            $result = ['location_name' => $location->name, 'distance_meters' => (int) round($distance), 'radius_meters' => $location->radius_meters, 'accuracy_meters' => (int) ceil($data['accuracy']),
                'profile_photo_url' => $request->user()->profile_photo_path ? route('profile.photo') : null];
            if ($distance > $location->radius_meters) {
                return response()->json($result + ['success' => false, 'message' => 'Scan not successful. You are outside the allowed school area.'], 422);
            }
            if ($distance + $data['accuracy'] > $location->radius_meters) {
                $requiredAccuracy = max(0, (int) floor($location->radius_meters - $distance));
                return response()->json($result + ['success' => false, 'message' => 'Your estimated position is near the school, but the device location is too uncertain to confirm check-in. For this position, accuracy must be '.$requiredAccuracy.' m or better. Enable precise location on your phone, move outdoors and scan again.'], 422);
            }
            if ($request->user()->role === 'administrator') {
                return response()->json($result + ['success' => true, 'status' => 'present', 'test_only' => true,
                    'teacher_name' => $request->user()->name, 'teacher_number' => $request->user()->profile_number,
                    'scanned_at' => now()->toIso8601String(), 'timezone' => config('app.timezone'),
                    'message' => 'Scan successful. You are inside the school area. Administrator test only; no teacher attendance saved.']);
            }
            $teacher = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($teacher->status, [null, 'active'], true), 403);
            $scanService = app(\App\Services\TeacherScanAttendance::class);
            if (($data['action'] ?? 'check_in') === 'check_out') {
                $scan = $scanService->checkout($teacher, [
                    'checkout_location_id' => $location->id,
                    'checkout_distance_meters' => (int) round($distance),
                    'checkout_accuracy_meters' => (int) ceil($data['accuracy']),
                ]);
                return response()->json($result + $scan + ['teacher_name' => $teacher->name,
                    'teacher_number' => $teacher->profile_number, 'timezone' => config('app.timezone')], $scan['success'] ? 200 : 422);
            }
            if ($scanService->sessions($teacher, today()->toDateString())->isNotEmpty()) {
                $scan = $scanService->scan($teacher);
                if (! $scan['success']) return response()->json($result + $scan, 422);
                if ($scan['fresh']) {
                    DB::table('school_location_check_ins')->insert([
                        'school_location_id' => $location->id, 'teacher_attendance_id' => $scan['attendance']->id,
                        'teacher_session_attendance_id' => $scan['session_record_id'],
                        'distance_meters' => (int) round($distance), 'accuracy_meters' => (int) ceil($data['accuracy']),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $savedLocation = DB::table('school_location_check_ins')->join('school_locations', 'school_locations.id', '=', 'school_location_check_ins.school_location_id')
                    ->where('teacher_session_attendance_id', $scan['session_record_id'])->value('school_locations.name');
                if ($savedLocation) $result['location_name'] = $savedLocation;
                unset($scan['attendance'], $scan['fresh'], $scan['session_record_id']);
                return response()->json($result + $scan + ['teacher_name' => $teacher->name, 'teacher_number' => $teacher->profile_number, 'timezone' => config('app.timezone')]);
            }
            $attendance = TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', today())->first();
            if (! $attendance) {
                $attendance = TeacherAttendance::create([
                    'teacher_id' => $teacher->id, 'date' => today()->toDateString(),
                    'program' => $teacher->program, 'status' => 'present', 'recorded_by' => $teacher->id,
                ]);
            }
            if ($attendance->wasRecentlyCreated) {
                DB::table('school_location_check_ins')->insert([
                    'school_location_id' => $location->id, 'teacher_attendance_id' => $attendance->id,
                    'distance_meters' => (int) round($distance), 'accuracy_meters' => (int) ceil($data['accuracy']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $checkIn = DB::table('school_location_check_ins')->where('teacher_attendance_id', $attendance->id)->orderBy('created_at')->first();
            return response()->json($result + ['success' => true,
                'teacher_name' => $teacher->name, 'teacher_number' => $teacher->profile_number,
                'timezone' => config('app.timezone'),
                'status' => $attendance->status,
                'scanned_at' => $checkIn ? \Carbon\Carbon::parse($checkIn->created_at)->toIso8601String() : null,
                'late_minutes' => null,
                'message' => $attendance->wasRecentlyCreated
                ? 'Scan successful. Your attendance has been recorded as present for today.'
                : 'Scan successful. Today’s attendance is already recorded as '.$attendance->status.'. No changes were made.']);
        });
    }
}
