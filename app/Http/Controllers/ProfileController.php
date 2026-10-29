<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('school.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        unset($data['photo']);
        $oldPhoto = $user->profile_photo_path;
        $newPhoto = null;

        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('profile-photos', 'local');
            abort_unless($newPhoto, 500, 'Unable to save your photo. Please try again.');
            $data['profile_photo_path'] = $newPhoto;
        }

        try {
            $user->forceFill($data)->save();
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }
            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return redirect()->route('profile.edit')->with('success', 'Your profile has been updated.');
    }

    public function userPhoto(Request $request, \App\Models\User $user)
    {
        $viewer = $request->user();
        $students = \App\Models\Student::visibleTo($viewer);
        $visible = $viewer->id === $user->id || $viewer->role === 'administrator'
            || (clone $students)->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('parent_id', $user->id))->exists()
            || ($user->role === 'teacher' && (
                \App\Support\SchoolModules::subjects($viewer)->where('teacher_id', $user->id)->exists()
                || \App\Models\SchoolClass::whereIn('id', (clone $students)->select('school_class_id'))->where('teacher_id', $user->id)->exists()
            ));
        abort_unless($visible, 403);
        $path = $user->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function studentPhoto(Request $request, \App\Models\Student $student)
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $student);
        abort_unless($student->photo && Storage::disk('public')->exists($student->photo), 404);

        return Storage::disk('public')->response($student->photo, null, ['Cache-Control' => 'private, no-store']);
    }

    public function photo(Request $request)
    {
        $path = $request->user()->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
