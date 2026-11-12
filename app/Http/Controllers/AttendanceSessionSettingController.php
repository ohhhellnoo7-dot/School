<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSessionSetting;
use App\Support\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSessionSettingController extends Controller
{
    public function edit()
    {
        return view('school.attendance.session-settings', ['settings' => AttendanceSessionSetting::all()->keyBy('session')]);
    }

    public function update(Request $request)
    {
        $rules = ['sessions' => 'required|array:Morning,Afternoon,Evening'];
        foreach (AttendanceSession::OPTIONS as $session) {
            $rules["sessions.$session"] = 'required|array:starts_at,ends_at,grace_minutes';
            $rules["sessions.$session.starts_at"] = 'nullable|date_format:H:i';
            $rules["sessions.$session.ends_at"] = 'nullable|date_format:H:i';
            if ($request->input("sessions.$session.starts_at") && $request->input("sessions.$session.ends_at")) {
                $rules["sessions.$session.ends_at"] .= "|after:sessions.$session.starts_at";
            }
            $rules["sessions.$session.grace_minutes"] = 'required|integer|between:0,120';
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data) {
            foreach (AttendanceSession::OPTIONS as $session) {
                AttendanceSessionSetting::updateOrCreate(['session' => $session], $data['sessions'][$session]);
            }
        });
        return redirect()->route('attendance-settings.edit')->with('success', 'Session start times saved. New scans will use these settings.');
    }
}
