<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\{TeacherManagementService, TimetableRoomReconciliation};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TimetableRoomController extends Controller
{
    public function review(Request $request, TimetableRoomReconciliation $service)
    {
        $lessons = Schedule::withoutGlobalScope('active')->with(['schoolClass','classroom'])->when($request->filled('lesson'), fn ($q) => $q->whereKey($request->integer('lesson')))->orderBy('id')->get();
        $details = collect(app(\App\Services\SchoolRoomAssignmentRepair::class)->scan()['records'])->keyBy('lesson_id');
        return view('school.timetables.rooms', ['lessons' => $lessons, 'scan' => $service->scan(), 'service' => $service, 'details'=>$details]);
    }

    public function preview(Request $request, TimetableRoomReconciliation $service)
    {
        if ($request->boolean('all_verified')) $plan = $service->globalPreview();
        else {
            $data = $request->validate(['selected' => 'required|array|min:1', 'selected.*' => 'integer|exists:schedules,id', 'rooms' => 'required|array','room_change_reasons'=>'nullable|array','room_change_reasons.*'=>'nullable|string|max:500']);
            $assignments = [];
            foreach ($data['selected'] as $id) {
                if (empty($data['rooms'][$id])) app(TeacherManagementService::class)->fail('Select a physical classroom for every selected lesson.');
                $assignments[$id] = $data['rooms'][$id];
            }
            $plan = $service->preview($assignments,$data['room_change_reasons']??[]);
        }
        $token = (string) Str::uuid();
        $request->session()->put('room_correction_preview', ['token' => $token, 'created' => time(), 'plan' => $plan]);
        return view('school.timetables.room-preview', compact('plan','token'));
    }

    public function confirm(Request $request, TimetableRoomReconciliation $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'token' => 'required|string']);
        $preview = $request->session()->get('room_correction_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) app(TeacherManagementService::class)->fail('Room preview expired or already used.');
        $report = $service->confirm($preview['plan'], $request->user()->id);
        $request->session()->forget('room_correction_preview');
        return redirect()->route('timetables.rooms.review')->with('room_repair_report', $report)->with('success', 'Confirmed room IDs corrected on the original lesson records.');
    }
}
