<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\TeacherManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TimetableResetController extends Controller
{
    public function reset(Request $request, TeacherManagementService $timetables)
    {
        $data = $request->validate(['password' => 'required|string|max:72']);

        $timetables->locked(function () use ($data) {
            $settings = DB::table('timetable_reset_settings')->where('id', 1);
            $state = $settings->first();
            $this->verifyPassword($data['password'], $state->password_hash, 'password');
            $now = now();
            if ($state->restore_until && $now->lt($state->restore_until)) {
                throw ValidationException::withMessages(['reset' => __('A schedule reset can still be restored. Restore it or wait until its undo window ends.')]);
            }

            $batch = Str::uuid()->toString();
            $count = Schedule::query()->update(['cancelled_at' => $now, 'reset_batch' => $batch]);
            if (! $count) {
                throw ValidationException::withMessages(['reset' => __('There are no schedules to reset.')]);
            }
            $settings->update(['batch' => $batch, 'restore_until' => $now->copy()->addMinutes(30)]);
        });

        return back()->with('success', __('All schedules were reset. You can restore them within 30 minutes.'));
    }

    public function restore(Request $request, TeacherManagementService $timetables)
    {
        $data = $request->validate(['batch' => 'required|uuid']);

        $timetables->locked(function () use ($data) {
            $settings = DB::table('timetable_reset_settings')->where('id', 1);
            $state = $settings->first();
            if ($state->batch !== $data['batch'] || ! $state->restore_until || now()->gte($state->restore_until)) {
                throw ValidationException::withMessages(['restore' => __('This schedule reset can no longer be restored.')]);
            }
            if (Schedule::query()->exists()) {
                throw ValidationException::withMessages(['restore' => __('New lessons have been scheduled. Resolve those lessons before restoring the previous schedule.')]);
            }

            Schedule::withoutGlobalScope('active')->where('reset_batch', $state->batch)
                ->update(['cancelled_at' => null, 'reset_batch' => null]);
            $settings->update(['batch' => null, 'restore_until' => null]);
        });

        return back()->with('success', __('The previous schedules were restored.'));
    }

    public function updatePassword(Request $request, TeacherManagementService $timetables)
    {
        $data = $request->validate([
            'current_password' => 'required|string|max:72',
            'password' => 'required|string|min:4|max:72|confirmed',
        ]);
        if (strlen($data['password']) > 72) {
            throw ValidationException::withMessages(['password' => __('Use a shorter reset password.')]);
        }

        $timetables->locked(function () use ($data) {
            $settings = DB::table('timetable_reset_settings')->where('id', 1);
            $this->verifyPassword($data['current_password'], $settings->first()->password_hash, 'current_password');
            $settings->update(['password_hash' => Hash::make($data['password'])]);
        });

        return back()->with('success', __('The timetable reset password was changed.'));
    }

    private function verifyPassword(string $password, string $hash, string $field): void
    {
        if (strlen($password) > 72 || ! password_verify($password, $hash)) {
            throw ValidationException::withMessages([$field => __('The reset password is incorrect.')]);
        }
    }
}
