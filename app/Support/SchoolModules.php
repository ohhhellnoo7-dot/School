<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SchoolModules
{
    /** Form metadata is shared by validation, tables and accessible Blade forms. */
    public static function all(): array
    {
        return [
            'parents' => ['title' => 'Parents & guardians', 'model' => User::class, 'icon' => 'people', 'fields' => ['profile_number' => 'text', 'name' => 'text', 'gender' => ['male', 'female', 'other'], 'email' => 'email', 'phone' => 'text', 'address' => 'textarea', 'status' => ['active', 'inactive'], 'password' => 'password']],
            'teachers' => ['title' => 'Teachers', 'model' => User::class, 'icon' => 'person-workspace', 'fields' => ['profile_number' => 'text', 'name' => 'text', 'program' => ['Khmer', 'English', 'Chinese'], 'gender' => ['male', 'female', 'other'], 'email' => 'email', 'phone' => 'text', 'address' => 'textarea', 'qualification' => 'text', 'hire_date' => 'date', 'salary' => 'number', 'status' => ['active', 'inactive'], 'password' => 'password']],
            'classes' => ['title' => 'Classes', 'model' => SchoolClass::class, 'icon' => 'building', 'fields' => ['name' => 'text', 'section' => 'text', 'program' => ['Khmer', 'Chinese', 'English'], 'session' => ['Morning', 'Afternoon', 'Evening'], 'room' => 'text', 'capacity' => 'number', 'teacher_id' => 'teacher']],
            'subjects' => ['title' => 'Subjects', 'model' => Subject::class, 'icon' => 'book', 'fields' => ['name' => 'text', 'code' => 'text', 'school_class_id' => 'class', 'teacher_id' => 'teacher']],
            'attendance' => ['title' => 'Attendance', 'model' => Attendance::class, 'icon' => 'calendar-check', 'fields' => ['student_id' => 'student', 'date' => 'date', 'status' => ['present', 'absent', 'late', 'excused'], 'notes' => 'text']],
            'examinations' => ['title' => 'Examinations', 'model' => Examination::class, 'icon' => 'journal-text', 'fields' => ['name' => 'text', 'subject_id' => 'subject', 'date' => 'date', 'total_marks' => 'number']],
            'grades' => ['title' => 'Results & grades', 'model' => Grade::class, 'icon' => 'award', 'fields' => ['student_id' => 'student', 'examination_id' => 'exam', 'marks' => 'number', 'remarks' => 'text']],
            'fees' => ['title' => 'School fees', 'model' => Fee::class, 'icon' => 'wallet2', 'fields' => ['student_id' => 'student', 'description' => 'text', 'amount' => 'number', 'paid' => 'number', 'due_date' => 'date', 'paid_at' => 'date']],
            'assignments' => ['title' => 'Assignments', 'model' => Assignment::class, 'icon' => 'clipboard-check', 'fields' => ['subject_id' => 'subject', 'title' => 'text', 'description' => 'textarea', 'due_date' => 'date']],
            'schedules' => ['title' => 'Timetable', 'model' => Schedule::class, 'icon' => 'clock', 'fields' => ['subject_id' => 'subject', 'day' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 'starts_at' => 'time', 'ends_at' => 'time', 'room' => 'text']],
            'announcements' => ['title' => 'Announcements', 'model' => Announcement::class, 'icon' => 'megaphone', 'fields' => ['title' => 'text', 'body' => 'textarea', 'audience' => ['all', 'administrator', 'teacher', 'student', 'parent'], 'published_at' => 'date']],
            'users' => ['title' => 'User accounts', 'model' => User::class, 'icon' => 'people', 'fields' => ['name' => 'text', 'email' => 'email', 'phone' => 'text', 'role' => ['administrator', 'teacher', 'student', 'parent'], 'password' => 'password']],
        ];
    }

    public static function get(string $module): array
    {
        return self::all()[$module] ?? abort(404);
    }

    public static function canWrite(User $user, string $module): bool
    {
        return $user->role === 'administrator' || ($user->role === 'teacher' && in_array($module, ['attendance', 'grades', 'assignments'], true));
    }

    public static function canView(User $user, string $module): bool
    {
        return $user->role === 'administrator' || match ($user->role) {
            'teacher' => in_array($module, ['classes', 'subjects', 'attendance', 'examinations', 'grades', 'assignments', 'schedules', 'announcements']),
            'student' => in_array($module, ['classes', 'subjects', 'attendance', 'examinations', 'grades', 'assignments', 'schedules', 'announcements', 'fees']),
            'parent' => in_array($module, ['attendance', 'examinations', 'grades', 'fees', 'announcements']),
            default => false,
        };
    }

    public static function subjects(User $user): Builder
    {
        $query = Subject::query();
        if ($user->role === 'administrator') {
            return $query;
        }
        if ($user->role === 'teacher') {
            return $query->where('teacher_id', $user->id);
        }

        return $query->whereIn('school_class_id', Student::visibleTo($user)->select('school_class_id'));
    }

    public static function query(string $module, User $user): Builder
    {
        $query = self::get($module)['model']::query();
        abort_unless(self::canView($user, $module), 403);
        if (in_array($module, ['teachers', 'parents'])) {
            $query->where('role', $module === 'teachers' ? 'teacher' : 'parent');
        }
        if ($user->role === 'administrator') {
            return $query;
        }

        return match ($module) {
            'announcements' => $query->whereIn('audience', ['all', $user->role])->whereDate('published_at', '<=', today()),
            'classes' => $query->whereIn('id', Student::visibleTo($user)->select('school_class_id'))->when($user->role === 'teacher', fn ($q) => $q->orWhere('teacher_id', $user->id)->orWhereIn('id', self::subjects($user)->select('school_class_id'))->orWhereIn('id', Schedule::where('teacher_id',$user->id)->select('school_class_id'))->orWhereIn('shared_timetable_class_id', Schedule::where('teacher_id',$user->id)->select('school_class_id'))),
            'subjects' => self::subjects($user),
            'attendance', 'fees' => $query->whereIn('student_id', Student::visibleTo($user)->select('id')),
            'grades' => $query->whereIn('student_id', Student::visibleTo($user)->select('id'))->when($user->role === 'teacher', fn ($q) => $q->whereHas('examination', fn ($exam) => $exam->whereIn('subject_id', self::subjects($user)->select('id')))),
            'schedules' => $user->role === 'teacher'
                ? $query->where('teacher_id', $user->id)
                : $query->whereIn('school_class_id', SchoolClass::whereIn('id', Student::visibleTo($user)->select('school_class_id'))->get()->map->timetableOwnerId()->unique()->all()),
            'examinations', 'assignments' => $query->whereIn('subject_id', self::subjects($user)->select('id')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function options(User $user): array
    {
        return [
            'teacher' => User::where('role', 'teacher')->orderBy('name')->pluck('name', 'id'),
            'class' => SchoolClass::orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label]),
            'student' => Student::visibleTo($user)->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name.' · '.$s->student_number]),
            'subject' => self::subjects($user)->with('schoolClass')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name.' · '.$s->schoolClass->label]),
            'exam' => Examination::with('subject')->whereIn('subject_id', self::subjects($user)->select('id'))->get()->mapWithKeys(fn ($e) => [$e->id => $e->name.' · '.$e->subject->name]),
        ];
    }
}
