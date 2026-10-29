<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\CambodianSchoolData as Data;

class SampleSchoolVerifier
{
    /** Read-only checks, safe to run against the configured school database. */
    public static function report(): array
    {
        $students = Data::students();
        $ids = $students->pluck('id');
        $classes = Data::classes();
        $subjects = Data::subjects();
        $subjectIds = $subjects->pluck('id');
        $exams = Examination::whereIn('subject_id', $subjectIds)->where('name', 'Cambodian sample midterm')->get();
        $grades = Grade::with(['student', 'examination.subject'])->whereIn('examination_id', $exams->pluck('id'))->get();
        $fees = Fee::whereIn('student_id', $ids)->whereIn('description', ['Tuition Fee', 'Registration Fee', 'Examination Fee', 'Transportation Fee', 'Library Fee'])->get();
        $slots = Schedule::with('subject')->whereIn('subject_id', $subjectIds)->get();
        $dates = [];
        $date = today()->subDay();
        while (count($dates) < 30) {
            if ($date->isWeekday()) {
                $dates[] = $date->toDateString();
            }
            $date->subDay();
        }
        $attendance = Attendance::whereIn('student_id', $ids)->whereDate('date', '>=', min($dates))->whereDate('date', '<=', max($dates))->get();
        $parents = User::whereIn('profile_number', array_map(fn ($i) => 'PAR'.Data::key($i), range(1, 50)))->withCount('children')->get();
        $teachers = User::whereIn('profile_number', array_map(fn ($i) => 'TCH'.Data::key($i), range(1, 20)))->withCount('subjects')->get();
        $announcementTitles = ['Welcome Back to School', 'Midterm Examination Schedule', 'Parent-Teacher Meeting', 'School Sports Day', 'Final Examination Preparation', 'School Holiday Announcement', 'Library Reading Week', 'Teacher Professional Development', 'Transportation Registration', 'Community Science Exhibition'];
        $announcements = Announcement::whereIn('title', $announcementTitles)->get();
        $checks = [
            '100 sample students' => $students->count() === 100,
            '20 sample teachers assigned to subjects' => $teachers->count() === 20 && $teachers->every(fn ($t) => $t->role === 'teacher' && $t->subjects_count > 0),
            '50 parents with two children each' => $parents->count() === 50 && $parents->every(fn ($p) => $p->role === 'parent' && $p->children_count === 2),
            '12 requested classes' => $classes->count() === 12,
            '10 subjects per requested class' => $subjects->count() === 120 && $subjects->groupBy('school_class_id')->every(fn ($group) => $group->count() === 10),
            '3000 attendance records over previous 30 weekdays' => $attendance->count() === 3000 && $attendance->every(fn ($a) => $a->date->isWeekday() && in_array($a->status, ['present', 'absent', 'late', 'excused'])),
            '72 class examinations and 600 valid results' => $exams->count() === 72 && $grades->count() === 600 && $grades->every(fn ($g) => $g->marks >= 0 && $g->marks <= $g->examination->total_marks && $g->student->school_class_id === $g->examination->subject->school_class_id && $g->examination->date->gte($g->student->enrollment_date)),
            '500 fees with valid amounts and payment dates' => $fees->count() === 500 && $fees->every(fn ($f) => $f->paid >= 0 && $f->paid <= $f->amount && ($f->paid == 0 || ($f->paid_at && $f->paid_at->lte(today())))),
            'all four payment statuses' => $fees->pluck('payment_status')->unique()->count() === 4,
            'two periods per class subject' => $subjects->every(fn ($s) => $slots->where('subject_id', $s->id)->count() >= 2),
            'new class subject timetable has no conflicts' => $slots->filter(fn ($s) => str_starts_with($s->subject->code, 'KH-'))->every(fn ($s) => ! TimetableConflicts::exists($s->subject, $s->day, $s->starts_at, $s->ends_at, $s->room, $s->id)),
            '10 announcements with authors' => $announcements->count() === 10 && $announcements->every(fn ($a) => $a->created_by !== null),
        ];

        return ['checks' => $checks, 'counts' => [
            'Sample students' => $students->count(), 'Sample teachers' => $teachers->count(), 'Sample guardians' => $parents->count(),
            'Requested classes' => $classes->count(), 'Class subject assignments' => $subjects->count(),
            'Attendance in previous 30 weekdays' => $attendance->count(), 'Sample examinations' => $exams->count(),
            'Sample results' => $grades->count(), 'Sample fees (USD)' => $fees->count(), 'Subject timetable entries' => $slots->count(),
            'Sample announcements' => $announcements->count(),
            'Legacy subject schedule conflicts' => $slots->filter(fn ($s) => ! str_starts_with($s->subject->code, 'KH-') && TimetableConflicts::exists($s->subject, $s->day, $s->starts_at, $s->ends_at, $s->room, $s->id))->count(),
        ]];
    }
}
