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
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ExpandedSchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Sample school data cannot be added in production.');
        }

        DB::transaction(function () {
            $faker = Factory::create('en_US');
            $faker->seed(20261026);
            $password = Hash::make('School@2026!');
            $account = function (string $email, string $name, string $role) use ($password): User {
                $user = User::firstOrNew(['email' => $email]);
                if (! $user->exists) {
                    $user->fill(['name' => $name, 'password' => $password, 'phone' => '555-0100']);
                    $user->role = $role;
                    $user->save();
                }
                if ($user->role !== $role) {
                    throw new \RuntimeException('A sample email is already assigned to another role. No data was changed.');
                }

                return $user;
            };

            $subjectNames = ['Mathematics', 'English', 'Science', 'History', 'Computer Studies', 'Art'];
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

            for ($group = 0; $group < 8; $group++) {
                $level = 7 + intdiv($group, 2);
                $section = $group % 2 === 0 ? 'B' : 'C';
                $teacher = $account('demo.teacher'.($group + 1).'@school.test', $faker->name(), 'teacher');
                $class = SchoolClass::firstOrCreate(['name' => 'Grade '.$level, 'section' => $section], [
                    'teacher_id' => $teacher->id, 'room' => 'Room '.(201 + $group), 'capacity' => 35,
                ]);
                $students = collect();
                $parent = null;
                for ($position = 0; $position < 12; $position++) {
                    $index = $group * 12 + $position + 1;
                    $key = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
                    $gender = $index % 2 === 0 ? 'female' : 'male';
                    $name = $faker->firstName($gender).' '.$faker->lastName();
                    if ($position % 2 === 0) {
                        $parent = $account('demo.parent'.intdiv($index + 1, 2).'@school.test', $faker->name(), 'parent');
                    }
                    $user = $account('demo.student'.$key.'@school.test', $name, 'student');
                    // More recent months have more enrollments, giving useful chart variation.
                    $monthsAgo = match (true) {
                        $position < 4 => 0, $position < 7 => 1, $position < 9 => 2, default => $position - 6
                    };
                    $enrolled = today()->startOfMonth()->subMonths($monthsAgo)->addDays($index % 12);
                    if ($enrolled->isFuture()) {
                        $enrolled = today();
                    }
                    $student = Student::firstOrCreate(['student_number' => 'DEMO-'.now()->year.'-'.$key], [
                        'name' => $name, 'gender' => $gender,
                        'date_of_birth' => today()->subYears($level + 5)->subDays($index * 3)->toDateString(),
                        'email' => $user->email, 'phone' => '555-'.str_pad((string) (3000 + $index), 4, '0', STR_PAD_LEFT),
                        'address' => $faker->streetAddress().', Greenfield', 'school_class_id' => $class->id,
                        'user_id' => $user->id, 'parent_id' => $parent->id,
                        'guardian_name' => $parent->name, 'guardian_phone' => '555-'.(4000 + intdiv($index + 1, 2)),
                        'enrollment_date' => $enrolled->toDateString(), 'status' => 'active',
                    ]);
                    $students->push($student);

                    for ($offset = 0; $offset < 35; $offset++) {
                        $date = today()->subDays($offset);
                        if ($date->isWeekend() || $date->lt($student->enrollment_date)) {
                            continue;
                        }
                        if (! Attendance::where('student_id', $student->id)->whereDate('date', $date)->exists()) {
                            Attendance::create(['student_id' => $student->id, 'date' => $date->toDateString(),
                                'status' => match (($index + $offset) % 20) {
                                    0 => 'absent', 1 => 'late', 2 => 'excused', default => 'present'
                                },
                                'notes' => ($index + $offset) % 20 === 2 ? 'Guardian notified the school.' : null,
                            ]);
                        }
                    }

                    foreach (['Term tuition' => 1200, 'Learning materials' => 85, 'School activities' => 45] as $description => $amount) {
                        $paid = $index % 5 === 0 ? 0 : ($index % 3 === 0 ? $amount / 2 : $amount);
                        $paymentDate = today()->startOfMonth()->subMonths($monthsAgo)->addDays($index % 12);
                        Fee::firstOrCreate(['student_id' => $student->id, 'description' => $description], [
                            'amount' => $amount, 'paid' => $paid, 'due_date' => today()->addDays(14)->toDateString(),
                            'paid_at' => $paid > 0 ? min($paymentDate->toDateString(), today()->toDateString()) : null,
                        ]);
                    }
                }

                foreach ($subjectNames as $slot => $name) {
                    $subject = Subject::firstOrCreate(['code' => 'DEMO-G'.$level.$section.'-'.($slot + 1)], [
                        'name' => $name, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id,
                    ]);
                    foreach (['Weekly assessment', 'Midterm examination'] as $examIndex => $examName) {
                        $exam = Examination::firstOrCreate(['subject_id' => $subject->id, 'name' => $examName], [
                            'date' => today()->subDays(3 + $examIndex * 4)->toDateString(), 'total_marks' => 100,
                        ]);
                        foreach ($students as $position => $student) {
                            if ($exam->date->lt($student->enrollment_date)) {
                                continue;
                            }
                            $marks = 55 + ($student->id * 7 + $slot * 3 + $examIndex * 11) % 46;
                            Grade::firstOrCreate(['student_id' => $student->id, 'examination_id' => $exam->id], [
                                'marks' => $marks, 'remarks' => $marks >= 85 ? 'Excellent progress.' : ($marks >= 70 ? 'Good work. Keep practicing.' : 'Additional practice is recommended.'),
                            ]);
                        }
                    }
                    foreach ([1, 2] as $week) {
                        Assignment::firstOrCreate(['subject_id' => $subject->id, 'title' => $name.' - Week '.$week.' activities'], [
                            'description' => 'Review your class notes, complete exercises 1-10, and prepare a short summary to share in class.',
                            'due_date' => today()->addDays($week * 7)->toDateString(),
                        ]);
                    }
                    foreach ($days as $day) {
                        Schedule::firstOrCreate(['subject_id' => $subject->id, 'day' => $day], [
                            'starts_at' => sprintf('%02d:00', 8 + $slot), 'ends_at' => sprintf('%02d:50', 8 + $slot), 'room' => $class->room,
                        ]);
                    }
                }
            }

            foreach ([
                ['Sports day registration is open', 'Students can register with their class teacher for athletics, football, basketball, and team activities.', 'all'],
                ['Staff planning meeting', 'Teachers: please bring your lesson plans and assessment summaries to the staff meeting on Friday.', 'teacher'],
                ['Guardian contact information', 'Parents: please contact the school office if your emergency contact details have changed.', 'parent'],
                ['Study club after school', 'Students: join the library study club for homework support and exam preparation.', 'student'],
                ['Science exhibition', 'Our science exhibition celebrates creative projects from every class. Families are welcome to attend.', 'all'],
            ] as [$title, $body, $audience]) {
                Announcement::firstOrCreate(['title' => $title], ['body' => $body, 'audience' => $audience, 'published_at' => today()->toDateString()]);
            }
        });

        foreach (['Students' => Student::class, 'Teachers' => User::class, 'Classes' => SchoolClass::class, 'Subjects' => Subject::class, 'Attendance' => Attendance::class, 'Examinations' => Examination::class, 'Grades' => Grade::class, 'Fees' => Fee::class, 'Assignments' => Assignment::class, 'Timetable entries' => Schedule::class, 'Announcements' => Announcement::class] as $label => $model) {
            $count = $label === 'Teachers' ? $model::where('role', 'teacher')->count() : $model::count();
            $this->command?->info($label.': '.$count);
        }
    }
}
