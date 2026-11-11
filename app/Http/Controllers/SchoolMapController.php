<?php

namespace App\Http\Controllers;

use App\Models\SchoolLocation;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolMapController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['location' => 'nullable|string|max:300', 'site' => 'nullable|integer']);
        $savedLocations = SchoolLocation::orderBy('name')->get();
        $locations = $savedLocations->reject(fn ($site) => $site->isLegacyTest())->values();
        $selectedLocation = $locations->firstWhere('id', $data['site'] ?? null) ?? $locations->first();
        $location = trim($data['location'] ?? ($selectedLocation ? $selectedLocation->latitude.','.$selectedLocation->longitude : config('services.school_map.location', '')));

        return view('school.map', compact('location', 'locations', 'selectedLocation', 'savedLocations'));
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
        $location = SchoolLocation::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('school.map', ['site' => $location->id])->with('success', 'School attendance location saved.');
    }

    public function destroy(Request $request, SchoolLocation $location)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        DB::transaction(function () use ($location) {
            SchoolLocation::whereKey($location->id)->lockForUpdate()->firstOrFail()->delete();
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
            'sampled_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(2)->toIso8601String(), 'before_or_equal:'.now()->addSeconds(30)->toIso8601String()],
        ]);

        return DB::transaction(function () use ($request, $data) {
            $locations = SchoolLocation::attendanceEligible()->orderBy('id')->lockForUpdate()->get();
            abort_if($locations->isEmpty(), 404, 'No attendance zones are available.');
            $candidates = $locations->map(function ($location) use ($data) {
                $lat1 = deg2rad((float) $location->latitude);
                $lat2 = deg2rad((float) $data['latitude']);
                $deltaLat = $lat2 - $lat1;
                $deltaLng = deg2rad((float) $data['longitude'] - (float) $location->longitude);
                $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
                return ['location' => $location, 'distance' => 6371000 * 2 * asin(sqrt(min(1, max(0, $a))))];
            })->sortBy('distance');
            // Prefer a zone that fully contains the reported position and uncertainty.
            $match = $candidates->first(fn ($candidate) =>
                $candidate['distance'] + $data['accuracy'] <= $candidate['location']->radius_meters
            ) ?? $candidates->first(fn ($candidate) =>
                $candidate['distance'] <= $candidate['location']->radius_meters
            ) ?? $candidates->first();
            $location = $match['location'];
            $distance = $match['distance'];
            $result = ['location_name' => $location->name, 'distance_meters' => (int) round($distance), 'radius_meters' => $location->radius_meters, 'accuracy_meters' => (int) ceil($data['accuracy'])];
            if ($distance > $location->radius_meters) {
                return response()->json($result + ['success' => false, 'message' => 'Scan not successful. You are outside the allowed school area.'], 422);
            }
            if ($distance + $data['accuracy'] > $location->radius_meters) {
                $requiredAccuracy = max(0, (int) floor($location->radius_meters - $distance));
                return response()->json($result + ['success' => false, 'message' => 'Your estimated position is near the school, but the device location is too uncertain to confirm check-in. For this position, accuracy must be '.$requiredAccuracy.' m or better. Enable precise location on your phone, move outdoors and scan again.'], 422);
            }
            if ($request->user()->role === 'administrator') {
                return response()->json($result + ['success' => true, 'message' => 'Scan successful. You are inside the school area. Administrator test only; no teacher attendance saved.']);
            }
            $teacher = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($teacher->status, [null, 'active'], true), 403);
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

            return response()->json($result + ['success' => true, 'message' => $attendance->wasRecentlyCreated
                ? 'Scan successful. Your attendance has been recorded as present for today.'
                : 'Scan successful. Today’s attendance is already recorded as '.$attendance->status.'. No changes were made.']);
        });
    }
}
