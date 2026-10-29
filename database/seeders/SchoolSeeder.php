<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Demo data cannot be seeded in production.');
        }
        DB::transaction(function () {
            $account = function ($email, $name, $role) {
                $user = User::firstOrNew(['email' => $email]);
                if (! $user->exists) {
                    $user->fill(['name' => $name, 'password' => 'School@2026!', 'phone' => '555-0100']);
                    $user->role = $role;
                    $user->save();
                }

                return $user;
            };
            $account('admin@school.test', 'Alex Morgan', 'administrator');
            $teacher = $account('teacher@school.test', 'Sarah Williams', 'teacher');
            $teacher2 = $account('james@school.test', 'James Anderson', 'teacher');
            $parent = $account('parent@school.test', 'Olivia Parker', 'parent');
            $studentAccount = $account('student@school.test', 'Emma Parker', 'student');
            $names = ['Emma Parker', 'Liam Johnson', 'Sophia Chen', 'Noah Williams', 'Isabella Davis', 'Oliver Brown', 'Amelia Wilson', 'Elijah Taylor', 'Mia Thompson', 'Lucas Martin', 'Charlotte Lee', 'Henry White', 'Harper Clark', 'Theodore Lewis', 'Evelyn Walker', 'Leo Hall', 'Aria Allen', 'Jack Young', 'Ella King', 'William Scott', 'Lily Green', 'Benjamin Adams', 'Grace Baker', 'Daniel Nelson'];
            $classes = collect();
            foreach (['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'] as $i => $name) {
                $classes->push(SchoolClass::firstOrCreate(['name' => $name, 'section' => 'A'], ['room' => 'Room '.(101 + $i), 'capacity' => 35, 'teacher_id' => $i < 2 ? $teacher->id : $teacher2->id]));
            }
            foreach ($names as $i => $name) {
                $student = Student::firstOrCreate(['student_number' => 'GF-'.now()->year.'-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)], [
                    'name' => $name, 'email' => $i === 0 ? 'student@school.test' : 'student'.($i + 1).'@school.test',
                    'gender' => $i % 2 === 0 ? 'female' : 'male', 'date_of_birth' => now()->subYears(12 + intdiv($i, 6))->subDays($i * 13)->toDateString(),
                    'phone' => '555-'.str_pad(100 + $i, 4, '0', STR_PAD_LEFT), 'address' => (10 + $i).' Oak Avenue, Greenfield',
                    'school_class_id' => $classes[intdiv($i, 6)]->id, 'user_id' => $i === 0 ? $studentAccount->id : null,
                    'parent_id' => $i === 0 ? $parent->id : null, 'guardian_name' => $i === 0 ? 'Olivia Parker' : 'Guardian of '.$name,
                    'guardian_phone' => '555-0200', 'enrollment_date' => now()->startOfMonth()->subMonths($i % 6)->addDays($i % 10)->toDateString(), 'status' => 'active',
                ]);
                foreach (range(0, 9) as $day) {
                    $date = today()->subDays($day);
                    if ($date->isWeekend()) {
                        continue;
                    }
                    Attendance::firstOrCreate(['student_id' => $student->id, 'date' => $date->toDateString()], ['status' => ($i + $day) % 11 === 0 ? 'absent' : (($i + $day) % 9 === 0 ? 'late' : 'present')]);
                }
                Fee::firstOrCreate(['student_id' => $student->id, 'description' => 'Term tuition'], ['amount' => 1200, 'paid' => $i % 4 === 0 ? 600 : 1200, 'due_date' => today()->addDays(14), 'paid_at' => today()->startOfMonth()->subMonths($i % 6)->addDays(3)]);
            }
            foreach ($classes as $i => $class) {
                foreach (['Mathematics', 'English', 'Science'] as $j => $name) {
                    $subject = Subject::firstOrCreate(['code' => 'G'.($i + 7).'-'.['MATH', 'ENG', 'SCI'][$j]], ['name' => $name, 'school_class_id' => $class->id, 'teacher_id' => $class->teacher_id]);
                    $exam = Examination::firstOrCreate(['subject_id' => $subject->id, 'name' => 'Midterm assessment'], ['date' => today()->subDays(7), 'total_marks' => 100]);
                    foreach ($class->students as $k => $student) {
                        Grade::firstOrCreate(['student_id' => $student->id, 'examination_id' => $exam->id], ['marks' => 72 + ($k * 3 + $j) % 28, 'remarks' => 'Keep up the good work.']);
                    }
                    Assignment::firstOrCreate(['subject_id' => $subject->id, 'title' => $name.' practice journal'], ['description' => 'Complete this week’s practice exercises and bring your journal to the next lesson.', 'due_date' => today()->addDays(5)]);
                    Schedule::firstOrCreate(['subject_id' => $subject->id, 'day' => 'Monday'], ['starts_at' => sprintf('%02d:00', 8 + $j), 'ends_at' => sprintf('%02d:50', 8 + $j), 'room' => $class->room]);
                }
            }
            foreach ([['A new term, a fresh start', 'Welcome back to Greenfield Academy! Please review your class timetable and make sure your contact details are up to date.'], ['Parent–teacher meetings', 'Join us next week to celebrate progress and discuss the next steps in your child’s learning journey.'], ['Library reading challenge', 'Discover a new favorite book. Our school reading challenge is open to every student this month.']] as $i => [$title, $body]) {
                Announcement::firstOrCreate(['title' => $title], ['body' => $body, 'audience' => 'all', 'published_at' => today()->subDays($i)]);
            }
        });
    }
}
