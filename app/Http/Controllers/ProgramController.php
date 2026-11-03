<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ProgramController extends Controller
{
    public function destroy(string $program)
    {
        DB::transaction(function () use ($program) {
            $record = Program::where('name', $program)->lockForUpdate()->firstOrFail();
            $groups = DB::table('class_groups')->where('program', $record->name)->pluck('id');
            $classes = DB::table('school_classes')->where('program', $record->name)->pluck('id');
            $subjects = DB::table('subjects')->whereIn('school_class_id', $classes)->pluck('id');
            $schedules = DB::table('schedules')->whereIn('school_class_id', $classes)
                ->orWhereIn('subject_id', $subjects)->pluck('id');
            $studentAccounts = DB::table('students')->whereIn('school_class_id', $classes)->pluck('user_id')->filter();

            DB::table('lesson_room_corrections')->whereIn('schedule_id', $schedules)->delete();
            DB::table('schedules')->whereIn('id', $schedules)->delete();
            DB::table('examinations')->whereIn('subject_id', $subjects)->delete();
            DB::table('subjects')->whereIn('id', $subjects)->delete();
            // Attendance, grades and fees cascade from students.
            DB::table('students')->whereIn('school_class_id', $classes)->delete();
            DB::table('school_classes')->whereIn('shared_timetable_class_id', $classes)
                ->update(['shared_timetable_class_id' => null]);
            DB::table('school_classes')->whereIn('id', $classes)->delete();
            DB::table('classroom_bookings')->whereIn('class_group_id', $groups)->delete();
            DB::table('class_groups')->whereIn('id', $groups)->delete();

            // Keep employees and shared parent accounts; remove student logins
            // only when they no longer belong to a surviving student record.
            $accounts = DB::table('users')->where('role', 'student')
                ->where(fn ($q) => $q->where('program', $record->name)->orWhereIn('id', $studentAccounts))
                ->whereNotIn('id', DB::table('students')->whereNotNull('user_id')->select('user_id'))
                ->pluck('id');
            DB::table('announcements')->whereIn('created_by', $accounts)->update(['created_by' => null]);
            DB::table('sessions')->whereIn('user_id', $accounts)->delete();
            DB::table('users')->whereIn('id', $accounts)->delete();
            DB::table('users')->where('program', $record->name)->where('role', '!=', 'teacher')->update(['program' => null]);
            $record->delete();
        });

        return redirect()->route('classrooms.index')->with('success', $program.' program deleted.');
    }

    public function create()
    {
        return view('school.programs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:programs,name'],
            'sessions' => ['required', 'array', 'min:1', 'max:3'],
            'sessions.*' => ['required', 'distinct', Rule::in(['Morning', 'Afternoon', 'Evening'])],
        ]);
        if (collect(Program::names())->contains(fn ($name) => mb_strtolower($name) === mb_strtolower($data['name']))) {
            throw ValidationException::withMessages(['name' => 'A program with this name already exists.']);
        }
        Program::create($data);

        return redirect()->route('classrooms.index', ['program' => $data['name']])
            ->with('success', $data['name'].' program created. You can now select it for classes and classroom lessons.');
    }
}
