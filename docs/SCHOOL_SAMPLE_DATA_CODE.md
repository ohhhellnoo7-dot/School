# Complete school sample data implementation

These are the exact source files implemented or used by this change. Paths are relative to `D:\laravel_projects\com3ms1ms2`. The existing school migration is included for schema reference; it was not replaced. Follow [the running guide](SCHOOL_SAMPLE_DATA.md) to apply changes safely.

## `database/migrations/2026_10_26_000001_create_school_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->index();
            $table->string('phone')->nullable();
        });
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('section');
            $table->string('room')->nullable();
            $table->unsignedInteger('capacity')->default(40);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'section']);
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number')->unique();
            $table->string('name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_name');
            $table->string('guardian_phone');
            $table->date('enrollment_date');
            $table->string('photo')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_marks')->default(100);
            $table->timestamps();
        });
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks', 7, 2);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'examination_id']);
        });
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('due_date');
            $table->timestamps();
        });
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('all');
            $table->date('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'schedules', 'assignments', 'fees', 'grades', 'examinations', 'attendances', 'subjects', 'students', 'school_classes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'phone']));
    }
};
```

## `database/migrations/2026_10_26_000002_add_school_profile_details.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_number')->nullable()->unique();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('qualification')->nullable();
            $table->date('hire_date')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('status')->default('active');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->string('guardian_relationship')->nullable();
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('guardian_relationship'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['profile_number']);
            $table->dropColumn(['profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status']);
        });
    }
};
```

## `database/factories/StudentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $surname = fake()->randomElement(['Sok', 'Chan', 'Chea', 'Kim', 'Heng', 'Lim', 'Phan', 'Ros', 'Seng', 'Vann']);
        $given = fake()->randomElement(['Dara', 'Sophea', 'Sokha', 'Bopha', 'Rithy', 'Sreypov', 'Piseth', 'Sreyneang', 'Vichea', 'Malis']);

        return [
            'student_number' => 'KH-'.fake()->unique()->numerify('########'),
            'name' => $surname.' '.$given, 'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-12 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(), 'phone' => '012'.fake()->numerify('######'),
            'address' => 'House '.fake()->numberBetween(1, 200).', Street '.fake()->numberBetween(1, 500).', Phnom Penh, Cambodia',
            'school_class_id' => fn () => SchoolClass::firstOrCreate(['name' => 'Grade 7', 'section' => 'A'])->id,
            'guardian_name' => $surname.' Sokun', 'guardian_phone' => '015'.fake()->numerify('######'),
            'guardian_relationship' => 'father', 'enrollment_date' => today()->subMonths(6)->toDateString(), 'status' => 'active',
        ];
    }
}
```

## `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CambodianSchoolSeeder::class);
    }
}
```

## `database/seeders/CambodianSchoolSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CambodianSchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Fictional school data is disabled in production.');
        }
        DB::transaction(function () {
            $this->call([
                TeacherSeeder::class, ClassSeeder::class, SubjectSeeder::class, ParentSeeder::class,
                StudentSeeder::class, AttendanceSeeder::class, ExaminationSeeder::class,
                PaymentSeeder::class, TimetableSeeder::class, AnnouncementSeeder::class,
            ]);
        });
        $this->command?->info('Fictional Cambodian school data added. Existing records were preserved.');
    }
}
```

## `database/seeders/CambodianSchoolData.php`

```php
<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Str;

/** Stable sample identifiers distinguish this dataset from existing school records. */
class CambodianSchoolData
{
    public const SUBJECTS = ['Mathematics', 'English', 'Khmer', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science', 'Physical Education'];

    public const SURNAMES = ['Sok', 'Chan', 'Chea', 'Kim', 'Heng', 'Lim', 'Phan', 'Ros', 'Seng', 'Vann'];

    public const GIVEN = ['Dara', 'Sophea', 'Sokha', 'Bopha', 'Rithy', 'Sreypov', 'Piseth', 'Sreyneang', 'Vichea', 'Malis'];

    public static function key(int $number): string
    {
        return str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public static function address(int $number): string
    {
        $cities = ['Phnom Penh', 'Siem Reap', 'Battambang', 'Kampong Cham', 'Ta Khmau'];

        return 'House '.(10 + $number).', Street '.(20 + $number % 50).', '.$cities[$number % 5].', Cambodia';
    }

    public static function account(string $number, string $role, array $attributes): User
    {
        $email = strtolower($number).'@sample.school.test';
        $user = User::firstOrNew(['email' => $email]);
        if ($user->exists) {
            if ($user->role !== $role || $user->profile_number !== $number) {
                throw new \RuntimeException('Sample account identifier collision: '.$email);
            }

            return $user;
        }
        $user->fill($attributes + ['profile_number' => $number, 'status' => 'active', 'password' => Str::random(40)]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public static function classes()
    {
        return SchoolClass::whereIn('name', array_map(fn ($n) => 'Grade '.$n, range(7, 12)))
            ->whereIn('section', ['A', 'B'])->orderBy('name')->orderBy('section')->get()
            ->sortBy(fn ($c) => (int) substr($c->name, 6) * 2 + ($c->section === 'B' ? 1 : 0))->values();
    }

    public static function students()
    {
        return Student::whereIn('email', array_map(fn ($n) => 'stu'.self::key($n).'@sample.school.test', range(1, 100)))
            ->orderBy('student_number')->get();
    }

    public static function subjects()
    {
        return Subject::whereIn('school_class_id', self::classes()->pluck('id'))->whereIn('name', self::SUBJECTS)->orderBy('id')->get()->unique(fn ($s) => $s->school_class_id.'|'.$s->name)->values();
    }
}
```

## `database/seeders/TeacherSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $gender = $i % 2 ? 'male' : 'female';
            CambodianSchoolData::account('TCH'.CambodianSchoolData::key($i), 'teacher', [
                'name' => CambodianSchoolData::SURNAMES[($i - 1) % 10].' '.($i <= 10 ? 'Sovann' : 'Sothea'),
                'gender' => $gender, 'phone' => '011'.str_pad((string) (200000 + $i), 6, '0', STR_PAD_LEFT),
                'address' => CambodianSchoolData::address($i + 100),
                'qualification' => $i % 3 ? 'Bachelor of Education' : 'Master of Education',
                'hire_date' => today()->subYears(2 + $i % 8)->toDateString(), 'salary' => 450 + $i * 25,
            ]);
        }
    }
}
```

## `database/seeders/ClassSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(7, 12) as $grade) {
            foreach (['A', 'B'] as $sectionIndex => $section) {
                $index = ($grade - 7) * 2 + $sectionIndex;
                $teacher = User::where('profile_number', 'TCH'.CambodianSchoolData::key($index + 1))->firstOrFail();
                SchoolClass::firstOrCreate(['name' => 'Grade '.$grade, 'section' => $section], [
                    'teacher_id' => $teacher->id, 'room' => 'KH-'.($grade * 100 + $sectionIndex + 1), 'capacity' => 40,
                ]);
            }
        }
    }
}
```

## `database/seeders/SubjectSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CambodianSchoolData::classes() as $classIndex => $class) {
            foreach (CambodianSchoolData::SUBJECTS as $subjectIndex => $name) {
                $teacherIndex = $subjectIndex + ($classIndex % 2) * 10 + 1;
                $teacher = User::where('profile_number', 'TCH'.CambodianSchoolData::key($teacherIndex))->firstOrFail();
                Subject::firstOrCreate(['school_class_id' => $class->id, 'name' => $name], [
                    'code' => 'KH-G'.substr($class->name, 6).$class->section.'-'.($subjectIndex + 1),
                    'teacher_id' => $teacher->id,
                ]);
            }
        }
    }
}
```

## `database/seeders/ParentSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            CambodianSchoolData::account('PAR'.CambodianSchoolData::key($i), 'parent', [
                'name' => CambodianSchoolData::SURNAMES[($i - 1) % 10].' '.['Sokun', 'Srey Mom', 'Bunna', 'Srey Leak', 'Sambath'][intdiv($i - 1, 10)],
                'gender' => $i % 2 ? 'male' : 'female', 'phone' => '015'.(300000 + $i),
                'address' => CambodianSchoolData::address($i),
            ]);
        }
    }
}
```

## `database/seeders/StudentSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $classes = CambodianSchoolData::classes();
        for ($i = 1; $i <= 100; $i++) {
            $key = CambodianSchoolData::key($i);
            $family = intdiv($i - 1, 2) + 1;
            $parent = User::where('profile_number', 'PAR'.CambodianSchoolData::key($family))->firstOrFail();
            $class = $classes[($i - 1) % 12];
            $number = 'STU'.$key;
            $email = 'stu'.$key.'@sample.school.test';
            $existing = Student::where('student_number', $number)->orWhere('email', $email)->first();
            if ($existing) {
                if ($existing->student_number !== $number || $existing->email !== $email) {
                    throw new \RuntimeException('Student sample identifier collision: '.$number.'. Existing records were preserved.');
                }

                continue;
            }
            $gender = $i % 2 ? 'male' : 'female';
            $given = $gender === 'male' ? ['Dara', 'Sokha', 'Rithy', 'Piseth', 'Vichea'] : ['Sophea', 'Bopha', 'Sreypov', 'Sreyneang', 'Malis'];
            $attributes = Student::factory()->raw([
                'student_number' => $number, 'email' => $email,
                'name' => CambodianSchoolData::SURNAMES[($family - 1) % 10].' '.$given[intdiv($i - 1, 20)],
                'gender' => $gender, 'date_of_birth' => today()->subYears((int) substr($class->name, 6) + 5)->subDays($i % 180)->toDateString(),
                'phone' => '012'.(400000 + $i), 'address' => $parent->address,
                'school_class_id' => $class->id, 'parent_id' => $parent->id,
                'guardian_name' => $parent->name, 'guardian_phone' => $parent->phone,
                'guardian_relationship' => $parent->gender === 'male' ? 'father' : 'mother',
                'enrollment_date' => today()->startOfMonth()->subMonths(2 + $i % 4)->addDays($i % 20)->toDateString(),
                'status' => $i % 25 === 0 ? 'inactive' : 'active',
            ]);
            Student::create($attributes);
        }
    }
}
```

## `database/seeders/AttendanceSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $dates = [];
        $date = today()->subDay();
        while (count($dates) < 30) {
            if ($date->isWeekday()) {
                $dates[] = $date->toDateString();
            }
            $date->subDay();
        }
        foreach (CambodianSchoolData::students() as $index => $student) {
            foreach ($dates as $offset => $date) {
                if ($date < $student->enrollment_date->toDateString()) {
                    continue;
                }
                if (! Attendance::where('student_id', $student->id)->whereDate('date', $date)->exists()) {
                    Attendance::create(['student_id' => $student->id, 'date' => $date,
                        'status' => match (($index * 7 + $offset) % 20) {
                            0, 1 => 'absent', 2 => 'late', 3 => 'excused', default => 'present'
                        },
                        'notes' => 'Fictional sample attendance.',
                    ]);
                }
            }
            if (today()->isWeekday()) {
                if (! Attendance::where('student_id', $student->id)->whereDate('date', today())->exists()) {
                    Attendance::create(['student_id' => $student->id, 'date' => today()->toDateString(),
                        'status' => match ($index % 20) {
                            0, 1 => 'absent', 2 => 'late', 3 => 'excused', default => 'present'
                        },
                        'notes' => 'Fictional sample attendance for the dashboard.',
                    ]);
                }
            }
        }
    }
}
```

## `database/seeders/ExaminationSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Examination;
use App\Models\Grade;
use Illuminate\Database\Seeder;

class ExaminationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CambodianSchoolData::subjects()->whereIn('name', array_slice(CambodianSchoolData::SUBJECTS, 0, 6)) as $subject) {
            $exam = Examination::firstOrCreate(['subject_id' => $subject->id, 'name' => 'Cambodian sample midterm'], [
                'date' => today()->subWeek()->toDateString(), 'total_marks' => 100,
            ]);
            foreach (CambodianSchoolData::students()->where('school_class_id', $subject->school_class_id) as $student) {
                if ($exam->date->lt($student->enrollment_date)) {
                    continue;
                }
                $index = (int) substr($student->student_number, 3);
                $marks = 35 + ($index * 13 + $subject->id * 7) % 66;
                Grade::firstOrCreate(['student_id' => $student->id, 'examination_id' => $exam->id], [
                    'marks' => $marks, 'remarks' => $marks >= 80 ? 'Strong understanding.' : ($marks >= 60 ? 'Keep practicing.' : 'Extra learning support recommended.'),
                ]);
            }
        }
    }
}
```

## `database/seeders/PaymentSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Fee;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Tuition Fee' => 350, 'Registration Fee' => 25, 'Examination Fee' => 15, 'Transportation Fee' => 40, 'Library Fee' => 10];
        foreach (CambodianSchoolData::students() as $index => $student) {
            foreach ($categories as $category => $amount) {
                $kind = ($index + array_search($category, array_keys($categories))) % 4;
                $paid = match ($kind) {
                    0 => $amount, 2 => $amount / 2, default => 0
                };
                $paidDate = today()->startOfMonth()->subMonths($index % 6)->addDays(3);
                if ($paidDate->lt($student->enrollment_date)) {
                    $paidDate = $student->enrollment_date->copy();
                }
                if ($paidDate->gt(today())) {
                    $paidDate = today();
                }
                Fee::firstOrCreate(['student_id' => $student->id, 'description' => $category], [
                    'amount' => $amount, 'paid' => $paid,
                    'due_date' => ($kind === 3 ? today()->subDays(14) : today()->addDays(14))->toDateString(),
                    'paid_at' => $paid > 0 ? $paidDate->toDateString() : null,
                ]);
            }
        }
    }
}
```

## `database/seeders/TimetableSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Support\TimetableConflicts;
use Illuminate\Database\Seeder;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CambodianSchoolData::subjects() as $subject) {
            // Each of the ten subjects receives two one-hour periods per week.
            $existing = Schedule::where('subject_id', $subject->id)->count();
            for ($period = $existing; $period < 2; $period++) {
                $placed = false;
                foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
                    foreach ([7, 8, 9, 10, 13, 14, 15, 16] as $hour) {
                        $start = sprintf('%02d:00', $hour);
                        $end = sprintf('%02d:00', $hour + 1);
                        $room = $subject->schoolClass->room ?: 'KH-Class-'.$subject->school_class_id;
                        if (TimetableConflicts::exists($subject, $day, $start, $end, $room)) {
                            continue;
                        }
                        Schedule::create(['subject_id' => $subject->id, 'day' => $day, 'starts_at' => $start, 'ends_at' => $end, 'room' => $room]);
                        $placed = true;
                        break 2;
                    }
                }
                if (! $placed) {
                    throw new \RuntimeException('No conflict-free timetable slot remains for '.$subject->code.'. No sample data was committed.');
                }
            }
        }
    }
}
```

## `database/seeders/AnnouncementSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'administrator')->orderBy('id')->first();
        if (! $author) {
            $author = CambodianSchoolData::account('ADM001', 'administrator', ['name' => 'Sok Sovan', 'phone' => '011200999']);
        }
        $items = [
            ['Welcome Back to School', 'Welcome to a new term. Please confirm your class and contact details with the school office.', 'all'],
            ['Midterm Examination Schedule', 'Midterm assessments cover Mathematics, English, Khmer, Physics, Chemistry and Biology. Ask your subject teacher for your class schedule.', 'student'],
            ['Parent-Teacher Meeting', 'Guardians are invited to discuss learning progress and attendance with class teachers. Contact the office to book a meeting.', 'parent'],
            ['School Sports Day', 'Join our fictional school sports celebration. Bring drinking water, comfortable shoes and your team spirit.', 'all'],
            ['Final Examination Preparation', 'Revision sessions and practice papers are available from subject teachers. Build a balanced study plan.', 'student'],
            ['School Holiday Announcement', 'Please consult the school calendar for the upcoming holiday and confirm return dates with your class teacher.', 'all'],
            ['Library Reading Week', 'Explore Khmer and English stories during reading week. Return borrowed books to the library desk.', 'student'],
            ['Teacher Professional Development', 'Teachers will share lesson plans and classroom activities at the next professional development meeting.', 'teacher'],
            ['Transportation Registration', 'Parents may contact the office to discuss sample bus routes and transportation arrangements.', 'parent'],
            ['Community Science Exhibition', 'Families are invited to celebrate student projects in science and technology. Details are available from class teachers.', 'all'],
        ];
        foreach ($items as $index => [$title, $body, $audience]) {
            Announcement::firstOrCreate(['title' => $title], ['body' => $body, 'audience' => $audience,
                'published_at' => today()->subDays($index)->toDateString(), 'created_by' => $author->id]);
        }
    }
}
```

## `app/Models/User.php`

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $attributes = ['role' => 'student'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone', 'profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function children()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    protected function casts(): array
    {
        return [
            'hire_date' => 'date', 'salary' => 'decimal:2',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

## `app/Models/Student.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'enrollment_date' => 'date'];
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getAttendancePercentageAttribute(): float
    {
        $total = $this->attendances()->count();

        return $total ? round($this->attendances()->whereIn('status', ['present', 'late'])->count() * 100 / $total, 1) : 0;
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'administrator' => $query,
            'teacher' => $query->whereHas('schoolClass', fn ($q) => $q->where('teacher_id', $user->id)->orWhereHas('subjects', fn ($s) => $s->where('teacher_id', $user->id))),
            'parent' => $query->where('parent_id', $user->id),
            default => $query->where('user_id', $user->id),
        };
    }
}
```

## `app/Models/SchoolClass.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $guarded = [];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
```

## `app/Models/Subject.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
```

## `app/Models/Attendance.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
```

## `app/Models/Examination.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examination extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
```

## `app/Models/Grade.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $guarded = [];

    public function getPercentageAttribute(): float
    {
        return (float) $this->marks * 100 / max(1, $this->examination->total_marks);
    }

    public function getLetterGradeAttribute(): string
    {
        return match (true) {
            $this->percentage >= 90 => 'A', $this->percentage >= 80 => 'B',
            $this->percentage >= 70 => 'C', $this->percentage >= 60 => 'D', default => 'F',
        };
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }
}
```

## `app/Models/Fee.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function getBalanceAttribute(): string
    {
        return number_format((float) $this->amount - (float) $this->paid, 2, '.', '');
    }

    public function getPaymentStatusAttribute(): string
    {
        if ((float) $this->paid >= (float) $this->amount) {
            return 'paid';
        }
        if ($this->due_date->lt(today())) {
            return 'overdue';
        }

        return (float) $this->paid > 0 ? 'partially_paid' : 'pending';
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
```

## `app/Models/Schedule.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $guarded = [];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
```

## `app/Models/Announcement.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = [];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }
}
```

## `app/Support/SchoolModules.php`

```php
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
            'teachers' => ['title' => 'Teachers', 'model' => User::class, 'icon' => 'person-workspace', 'fields' => ['profile_number' => 'text', 'name' => 'text', 'gender' => ['male', 'female', 'other'], 'email' => 'email', 'phone' => 'text', 'address' => 'textarea', 'qualification' => 'text', 'hire_date' => 'date', 'salary' => 'number', 'status' => ['active', 'inactive'], 'password' => 'password']],
            'classes' => ['title' => 'Classes', 'model' => SchoolClass::class, 'icon' => 'building', 'fields' => ['name' => 'text', 'section' => 'text', 'room' => 'text', 'capacity' => 'number', 'teacher_id' => 'teacher']],
            'subjects' => ['title' => 'Subjects', 'model' => Subject::class, 'icon' => 'book', 'fields' => ['name' => 'text', 'code' => 'text', 'school_class_id' => 'class', 'teacher_id' => 'teacher']],
            'attendance' => ['title' => 'Attendance', 'model' => Attendance::class, 'icon' => 'calendar-check', 'fields' => ['student_id' => 'student', 'date' => 'date', 'status' => ['present', 'absent', 'late', 'excused'], 'notes' => 'text']],
            'examinations' => ['title' => 'Examinations', 'model' => Examination::class, 'icon' => 'journal-text', 'fields' => ['name' => 'text', 'subject_id' => 'subject', 'date' => 'date', 'total_marks' => 'number']],
            'grades' => ['title' => 'Results & grades', 'model' => Grade::class, 'icon' => 'award', 'fields' => ['student_id' => 'student', 'examination_id' => 'exam', 'marks' => 'number', 'remarks' => 'text']],
            'fees' => ['title' => 'School fees', 'model' => Fee::class, 'icon' => 'wallet2', 'fields' => ['student_id' => 'student', 'description' => 'text', 'amount' => 'number', 'paid' => 'number', 'due_date' => 'date', 'paid_at' => 'date']],
            'assignments' => ['title' => 'Assignments', 'model' => Assignment::class, 'icon' => 'clipboard-check', 'fields' => ['subject_id' => 'subject', 'title' => 'text', 'description' => 'textarea', 'due_date' => 'date']],
            'schedules' => ['title' => 'Timetable', 'model' => Schedule::class, 'icon' => 'clock', 'fields' => ['subject_id' => 'subject', 'day' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'starts_at' => 'time', 'ends_at' => 'time', 'room' => 'text']],
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
            'student' => in_array($module, ['classes', 'subjects', 'attendance', 'examinations', 'grades', 'assignments', 'schedules', 'announcements']),
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
            'classes' => $query->whereIn('id', Student::visibleTo($user)->select('school_class_id'))->when($user->role === 'teacher', fn ($q) => $q->orWhere('teacher_id', $user->id)->orWhereIn('id', self::subjects($user)->select('school_class_id'))),
            'subjects' => self::subjects($user),
            'attendance', 'fees' => $query->whereIn('student_id', Student::visibleTo($user)->select('id')),
            'grades' => $query->whereIn('student_id', Student::visibleTo($user)->select('id'))->when($user->role === 'teacher', fn ($q) => $q->whereHas('examination', fn ($exam) => $exam->whereIn('subject_id', self::subjects($user)->select('id')))),
            'examinations', 'assignments', 'schedules' => $query->whereIn('subject_id', self::subjects($user)->select('id')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function options(User $user): array
    {
        return [
            'teacher' => User::where('role', 'teacher')->orderBy('name')->pluck('name', 'id'),
            'class' => SchoolClass::orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->section]),
            'student' => Student::visibleTo($user)->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name.' · '.$s->student_number]),
            'subject' => self::subjects($user)->with('schoolClass')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name.' · '.$s->schoolClass->name.' '.$s->schoolClass->section]),
            'exam' => Examination::with('subject')->whereIn('subject_id', self::subjects($user)->select('id'))->get()->mapWithKeys(fn ($e) => [$e->id => $e->name.' · '.$e->subject->name]),
        ];
    }
}
```

## `app/Support/SchoolDependencies.php`

```php
<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SchoolDependencies
{
    public static function hasDependents(Model $record): bool
    {
        return match (true) {
            $record instanceof Student => $record->attendances()->exists() || $record->grades()->exists() || $record->fees()->exists(),
            $record instanceof User => $record->children()->exists() || $record->subjects()->exists() || $record->classes()->exists()
                || Student::where('user_id', $record->id)->exists() || Announcement::where('created_by', $record->id)->exists(),
            $record instanceof SchoolClass => $record->students()->exists() || $record->subjects()->exists(),
            $record instanceof Subject => Examination::where('subject_id', $record->id)->exists()
                || Assignment::where('subject_id', $record->id)->exists() || Schedule::where('subject_id', $record->id)->exists(),
            $record instanceof Examination => Grade::where('examination_id', $record->id)->exists(),
            default => false,
        };
    }
}
```

## `app/Support/TimetableConflicts.php`

```php
<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Subject;

class TimetableConflicts
{
    public static function exists(Subject $subject, string $day, string $start, string $end, string $room, ?int $ignore = null): bool
    {
        return Schedule::where('day', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->where(function ($q) use ($subject, $room) {
                $q->where('room', $room)->orWhereHas('subject', function ($q) use ($subject) {
                    $q->where('school_class_id', $subject->school_class_id);
                    if ($subject->teacher_id) {
                        $q->orWhere('teacher_id', $subject->teacher_id);
                    }
                });
            })->exists();
    }
}
```

## `app/Support/SampleSchoolVerifier.php`

```php
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
```

## `app/Http/Controllers/StudentController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolDependencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'class' => 'nullable|integer|exists:school_classes,id', 'status' => 'nullable|in:active,inactive,graduated', 'sort' => 'nullable|in:name,student_number,gender,date_of_birth,enrollment_date,email,phone,status,school_class_id', 'direction' => 'nullable|in:asc,desc']);
        $students = Student::visibleTo($request->user())->with('schoolClass')
            ->when($request->search, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('student_number', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($request->class, fn ($q, $class) => $q->where('school_class_id', $class))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))->orderBy('id')->paginate(12)->withQueryString();

        return view('school.students.index', ['students' => $students, 'classes' => SchoolClass::orderBy('name')->get()]);
    }

    public function create()
    {
        Gate::authorize('create', Student::class);

        return $this->form(new Student);
    }

    public function edit(Student $student)
    {
        Gate::authorize('update', $student);

        return $this->form($student);
    }

    private function form(Student $student)
    {
        return view('school.students.form', ['student' => $student, 'classes' => SchoolClass::orderBy('name')->get(), 'parents' => User::where('role', 'parent')->get(), 'accounts' => User::where('role', 'student')->get()]);
    }

    public function show(Student $student)
    {
        Gate::authorize('view', $student);

        return view('school.students.show', ['student' => $student->load(['schoolClass', 'parent', 'attendances' => fn ($q) => $q->latest('date')->limit(30), 'grades.examination.subject', 'fees'])]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Student::class);
        $data = $this->validated($request);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
        }
        $student = Student::create($data);

        return redirect()->route('students.show', $student)->with('success', 'Student enrolled successfully.');
    }

    public function update(Request $request, Student $student)
    {
        Gate::authorize('update', $student);
        $data = $this->validated($request, $student);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
        } else {
            unset($data['photo']);
        }
        $student->update($data);

        return redirect()->route('students.show', $student)->with('success', 'Student updated.');
    }

    public function destroy(Student $student)
    {
        Gate::authorize('delete', $student);
        if (SchoolDependencies::hasDependents($student)) {
            return back()->withErrors(['record' => 'This student has attendance, grades or fees. Reassign or remove those records before deleting the student.']);
        }
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student and related records deleted.');
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'student_number' => ['required', 'string', 'max:30', Rule::unique('students')->ignore($student)],
            'name' => 'required|string|max:255', 'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'email' => ['required', 'email', 'max:255', Rule::unique('students')->ignore($student)],
            'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:2000',
            'school_class_id' => 'required|exists:school_classes,id',
            'parent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'parent')],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'student'), Rule::unique('students')->ignore($student)],
            'guardian_relationship' => 'nullable|in:father,mother,guardian',
            'guardian_name' => 'required|string|max:255', 'guardian_phone' => 'required|string|max:30',
            'enrollment_date' => 'required|date', 'status' => 'required|in:active,inactive,graduated',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        if ($student && (int) $data['school_class_id'] !== $student->school_class_id && $student->grades()->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'A student with examination results cannot be moved to another class without archiving those results.']);
        }

        return $data;
    }
}
```

## `app/Http/Controllers/SchoolModuleController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolDependencies;
use App\Support\SchoolModules;
use App\Support\TimetableConflicts;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SchoolModuleController extends Controller
{
    public function index(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $query = SchoolModules::query($module, $request->user());
        $request->validate(['search' => 'nullable|string|max:255', 'direction' => 'nullable|in:asc,desc', 'sort' => 'nullable|string', 'filter' => 'nullable|string|max:255', 'date' => 'nullable|date', 'teacher' => 'nullable|integer|exists:users,id']);
        $fields = array_keys(array_filter($meta['fields'], fn ($type) => in_array($type, ['text', 'email', 'textarea'], true)));
        if ($request->filled('search')) {
            $query->where(function ($q) use ($fields, $request, $module) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%'.$request->search.'%');
                }
                if (in_array($module, ['fees', 'attendance', 'grades'])) {
                    $q->orWhereHas('student', fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('student_number', 'like', '%'.$request->search.'%'));
                }
            });
        }
        $filterFields = isset($meta['fields']['status']) ? ['status' => $meta['fields']['status']] : $meta['fields'];
        foreach ($filterFields as $field => $type) {
            if (is_array($type) && $request->filled('filter')) {
                $query->where($field, $request->filter);
                break;
            }
        }
        if ($module === 'attendance' && $request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($module === 'classes' && $request->filled('teacher')) {
            $query->where('teacher_id', $request->teacher);
        }
        if ($module === 'fees' && $request->filled('filter')) {
            match ($request->filter) {
                'paid' => $query->whereColumn('paid', '>=', 'amount'),
                'overdue' => $query->whereColumn('paid', '<', 'amount')->whereDate('due_date', '<', today()),
                'partially_paid' => $query->where('paid', '>', 0)->whereColumn('paid', '<', 'amount')->whereDate('due_date', '>=', today()),
                'pending' => $query->where('paid', 0)->whereColumn('paid', '<', 'amount')->whereDate('due_date', '>=', today()),
                default => null,
            };
        }
        if (in_array($module, ['teachers', 'parents'])) {
            $query->with(['subjects', 'children']);
        }
        if ($module === 'classes') {
            $query->with('teacher')->withCount('students');
        }
        if ($module === 'grades') {
            $query->with('examination');
        }
        $sortable = array_merge(['id'], array_keys(array_filter($meta['fields'], fn ($type) => $type !== 'password')));
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';

        return view('school.modules.index', ['module' => $module, 'meta' => $meta,
            'records' => $query->orderBy($sort, $request->input('direction', 'desc'))->orderBy('id')->paginate(15)->withQueryString(),
            'options' => SchoolModules::options($request->user())]);
    }

    public function show(Request $request, string $module, int $id)
    {
        $meta = SchoolModules::get($module);
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);

        return view('school.modules.show', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function create(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $record = new $meta['model'];
        Gate::authorize('manage', [$record, $module]);

        return view('school.modules.form', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function edit(Request $request, string $module, int $id)
    {
        $meta = SchoolModules::get($module);
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);

        return view('school.modules.form', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function store(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $record = new $meta['model'];
        Gate::authorize('manage', [$record, $module]);
        $data = $this->validated($request, $module, $record);
        DB::transaction(function () use ($record, $data, $module) {
            $record->fill($data);
            if (in_array($module, ['teachers', 'parents'])) {
                $record->role = $module === 'teachers' ? 'teacher' : 'parent';
            }
            if ($module === 'users') {
                $record->role = $data['role'];
            }
            if ($module === 'announcements') {
                $record->created_by = auth()->id();
            }
            $record->save();
        });

        return redirect()->route('modules.index', $module)->with('success', 'Record created successfully.');
    }

    public function update(Request $request, string $module, int $id)
    {
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);
        $data = $this->validated($request, $module, $record);
        if ($record instanceof User && isset($data['role']) && $data['role'] !== $record->role) {
            // Linked records must be explicitly unlinked before an account changes purpose.
            if (Student::where('user_id', $record->id)->orWhere('parent_id', $record->id)->exists()
                || Subject::where('teacher_id', $record->id)->exists()
                || SchoolClass::where('teacher_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['role' => 'Unlink this account from students and teaching assignments before changing its role.']);
            }
            if ($record->id === $request->user()->id) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator access.']);
            }
        }
        if ($module === 'users') {
            $record->role = $data['role'];
        }
        $record->fill($data)->save();

        return redirect()->route('modules.index', $module)->with('success', 'Record updated successfully.');
    }

    public function destroy(Request $request, string $module, int $id)
    {
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);
        if ($record instanceof User && $record->id === $request->user()->id) {
            return back()->withErrors(['record' => 'You cannot delete your own account.']);
        }
        if (SchoolDependencies::hasDependents($record)) {
            return back()->withErrors(['record' => 'This record has linked school records. Reassign or remove those records before deleting it.']);
        }
        try {
            $record->delete();
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                return back()->withErrors(['record' => 'This record is in use. Remove or reassign its dependent records first.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Record deleted.');
    }

    private function authorizeTeacherGrade(Request $request, $record): void
    {
        if ($request->user()->role === 'teacher' && $record instanceof Grade) {
            abort_unless(SchoolModules::subjects($request->user())->whereKey($record->examination->subject_id)->exists(), 403);
        }
    }

    private function validated(Request $request, string $module, $record): array
    {
        $rules = [];
        $meta = SchoolModules::get($module);
        $optional = ['phone', 'notes', 'remarks', 'paid_at', 'teacher_id', 'profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status'];
        $options = SchoolModules::options($request->user());
        foreach ($meta['fields'] as $field => $type) {
            $rules[$field] = [in_array($field, $optional) || ($field === 'password' && $record->exists) ? 'nullable' : 'required'];
            if (is_array($type)) {
                $rules[$field][] = Rule::in($type);
            } elseif (in_array($type, ['teacher', 'class', 'student', 'subject', 'exam'])) {
                $rules[$field][] = Rule::in($options[$type]->keys()->all());
            } else {
                $rules[$field] = array_merge($rules[$field], match ($type) {
                    'number' => ['numeric', 'min:0', 'max:99999999'],
                    'email' => ['email', 'max:255', Rule::unique('users', 'email')->ignore($record->id)],
                    'date' => ['date'], 'time' => ['date_format:H:i'],
                    'password' => ['string', 'max:255', Password::min(10)->mixedCase()->numbers()],
                    'textarea' => ['string', 'max:10000'], default => ['string', 'max:255'],
                });
            }
        }
        if (in_array($module, ['teachers', 'parents', 'users']) && isset($rules['profile_number'])) {
            $rules['profile_number'][] = Rule::unique('users', 'profile_number')->ignore($record->id);
        }
        if (isset($rules['salary'])) {
            $rules['salary'][] = 'decimal:0,2';
        }
        if ($module === 'subjects') {
            $rules['code'][] = Rule::unique('subjects', 'code')->ignore($record->id);
        }
        if ($module === 'classes') {
            $rules['capacity'] = ['required', 'integer', 'min:1', 'max:1000'];
            $rules['section'][] = Rule::unique('school_classes')->where('name', $request->input('name'))->ignore($record->id);
        }
        if ($module === 'examinations') {
            $rules['total_marks'] = ['required', 'integer', 'min:1', 'max:10000'];
        }
        if ($module === 'attendance') {
            $rules['date'][] = 'before_or_equal:today';
            $rules['date'][] = function ($attribute, $value, $fail) use ($request, $record) {
                if (Attendance::where('student_id', $request->input('student_id'))->whereDate('date', $value)->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))->exists()) {
                    $fail('Attendance has already been recorded for this student on this date.');
                }
            };
        }
        if ($module === 'grades') {
            $rules['examination_id'][] = Rule::unique('grades')->where('student_id', $request->input('student_id'))->ignore($record->id);
        }
        if ($module === 'fees') {
            $rules['paid'][] = 'lte:amount';
            $rules['amount'][] = 'decimal:0,2';
            $rules['paid'][] = 'decimal:0,2';
            $rules['paid_at'][] = 'before_or_equal:today';
            $rules['paid_at'][] = Rule::requiredIf(fn () => (float) $request->input('paid', 0) > 0);
        }
        if ($module === 'schedules') {
            $rules['ends_at'][] = 'after:starts_at';
        }
        $data = $request->validate($rules);
        if ($module === 'schedules') {
            $subject = Subject::findOrFail($data['subject_id']);
            $validWindow = ($data['starts_at'] >= '07:00' && $data['ends_at'] <= '11:00')
                || ($data['starts_at'] >= '13:00' && $data['ends_at'] <= '17:00');
            if (! $validWindow || TimetableConflicts::exists($subject, $data['day'], $data['starts_at'], $data['ends_at'], $data['room'], $record->id)) {
                throw ValidationException::withMessages(['starts_at' => 'Use school hours (07:00?11:00 or 13:00?17:00) without overlapping a teacher, class or classroom.']);
            }
        }
        if ($module === 'attendance') {
            $date = Carbon::parse($data['date']);
            if ($date->isWeekend() || $date->lt(Student::findOrFail($data['student_id'])->enrollment_date)) {
                throw ValidationException::withMessages(['date' => 'Choose a weekday on or after the student enrollment date.']);
            }
        }
        if ($record->exists && $module === 'subjects' && ((int) ($data['teacher_id'] ?? 0) !== (int) $record->teacher_id || (int) $data['school_class_id'] !== $record->school_class_id) && Schedule::where('subject_id', $record->id)->exists()) {
            $candidate = clone $record;
            $candidate->teacher_id = $data['teacher_id'] ?? null;
            $candidate->school_class_id = $data['school_class_id'];
            foreach (Schedule::where('subject_id', $record->id)->get() as $slot) {
                if (TimetableConflicts::exists($candidate, $slot->day, $slot->starts_at, $slot->ends_at, $slot->room, $slot->id)) {
                    throw ValidationException::withMessages(['teacher_id' => 'The teacher already has another class during this subject timetable.']);
                }
            }
        }
        if ($record->exists && $module === 'examinations' && Grade::where('examination_id', $record->id)->exists()) {
            if ($data['total_marks'] < Grade::where('examination_id', $record->id)->max('marks')) {
                throw ValidationException::withMessages(['total_marks' => 'The examination total cannot be lower than marks already awarded.']);
            }
            if ((int) $data['subject_id'] !== $record->subject_id) {
                throw ValidationException::withMessages(['subject_id' => 'An examination with published results cannot be moved to another subject.']);
            }
        }
        if ($record->exists && $module === 'subjects' && (int) $data['school_class_id'] !== $record->school_class_id && Examination::where('subject_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'A subject with examinations cannot be moved to another class.']);
        }
        if ($module === 'grades') {
            $exam = Examination::with('subject')->findOrFail($data['examination_id']);
            $student = Student::findOrFail($data['student_id']);
            if ($exam->subject->school_class_id !== $student->school_class_id || $data['marks'] > $exam->total_marks) {
                throw ValidationException::withMessages(['marks' => 'Choose an exam for this student’s class and marks within the examination total.']);
            }
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
```

## `app/Http/Controllers/DashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolModules;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('school.dashboard', $this->dashboardData($request));
    }

    public function data(Request $request)
    {
        $data = $this->dashboardData($request);

        return response()->json([
            'html' => view('school.partials.dashboard-content', $data)->render(),
            'charts' => $data['charts'],
            'showRevenue' => in_array($request->user()->role, ['administrator', 'parent'], true),
            'summary' => collect($data)->only(['studentCount', 'teacherCount', 'classCount', 'subjectCount', 'presentCount', 'absentCount', 'attendanceCount', 'collected', 'pending']),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function dashboardData(Request $request): array
    {
        $user = $request->user();
        $students = Student::visibleTo($user);
        $ids = (clone $students)->select('id');
        $attendance = Attendance::whereIn('student_id', $ids)->whereDate('date', today());
        $fees = Fee::whereIn('student_id', $ids);
        $canViewFees = in_array($user->role, ['administrator', 'parent'], true);
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $charts = [
            'labels' => $months->map(fn ($date) => $date->format('M'))->values(),
            'enrollment' => $months->map(fn ($date) => (clone $students)->whereBetween('enrollment_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->count())->values(),
            // Revenue is grouped by the date payment was recorded on each fee record.
            'revenue' => $canViewFees ? $months->map(fn ($date) => (float) (clone $fees)->whereBetween('paid_at', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->sum('paid'))->values() : [],
            'gender' => collect(['female', 'male', 'other'])->map(fn ($gender) => (clone $students)->where('gender', $gender)->count()),
            'attendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (clone $attendance)->where('status', $status)->count()),
        ];

        return [
            'studentCount' => (clone $students)->count(),
            'teacherCount' => $user->role === 'administrator' ? User::where('role', 'teacher')->count() : SchoolModules::subjects($user)->whereNotNull('teacher_id')->distinct()->count('teacher_id'),
            'classCount' => $user->role === 'administrator' ? SchoolClass::count() : ($user->role === 'teacher' ? SchoolModules::query('classes', $user)->count() : (clone $students)->distinct()->count('school_class_id')),
            'subjectCount' => SchoolModules::subjects($user)->count(),
            'presentCount' => (clone $attendance)->where('status', 'present')->count(),
            'absentCount' => (clone $attendance)->where('status', 'absent')->count(),
            'attendanceCount' => (clone $attendance)->count(), 'collected' => $canViewFees ? (clone $fees)->sum('paid') : null,
            'pending' => $canViewFees ? (float) (clone $fees)->sum('amount') - (float) (clone $fees)->sum('paid') : null,
            'recent' => (clone $students)->with('schoolClass')->latest()->latest('id')->limit(5)->get(),
            'announcements' => Announcement::whereIn('audience', ['all', $user->role])->whereDate('published_at', '<=', today())->latest('published_at')->limit(4)->get(),
            'charts' => $charts,
        ];
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $classes = SchoolClass::withCount('students')->with('teacher')->get();
        $date = $request->validate(['date' => 'nullable|date'])['date'] ?? today()->toDateString();
        $attendance = Attendance::whereDate('date', $date)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $totals = ['billed' => Fee::sum('amount'), 'paid' => Fee::sum('paid')];

        return view('school.reports', compact('classes', 'date', 'attendance', 'totals'));
    }
}
```

## `routes/web.php`

```php
<?php

use App\Http\Controllers\HiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});
Route::get('hi/{name}', [HiController::class, 'greet']);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\StudentController;

// Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
// Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
// Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
// Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
// Route::get('/customers/{id}/edit/', [CustomerController::class, 'edit'])->name('customers.edit');
// Route::patch('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
// Route::delete('/customers/{id}', [CustomerController::class,'destroy'])->name('customers.destroy');
Route::resource('/customers', CustomerController::class)->middleware(['auth', 'role:administrator']);

Route::middleware('guest')->group(function () {
    Route::view('/login', 'school.auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/register', 'school.auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});
Route::middleware(['auth', 'role:administrator,teacher,student,parent'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/reports', [DashboardController::class, 'reports'])->middleware('role:administrator')->name('reports');
    Route::resource('/students', StudentController::class);
    Route::get('/school/{module}', [SchoolModuleController::class, 'index'])->name('modules.index');
    Route::get('/school/{module}/create', [SchoolModuleController::class, 'create'])->name('modules.create');
    Route::post('/school/{module}', [SchoolModuleController::class, 'store'])->name('modules.store');
    Route::get('/school/{module}/{id}', [SchoolModuleController::class, 'show'])->whereNumber('id')->name('modules.show');
    Route::get('/school/{module}/{id}/edit', [SchoolModuleController::class, 'edit'])->name('modules.edit');
    Route::put('/school/{module}/{id}', [SchoolModuleController::class, 'update'])->name('modules.update');
    Route::delete('/school/{module}/{id}', [SchoolModuleController::class, 'destroy'])->name('modules.destroy');
});
```

## `routes/console.php`

```php
<?php

use App\Support\SampleSchoolVerifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('school:verify-sample', function () {
    $report = SampleSchoolVerifier::report();
    $this->table(['Dataset', 'Count'], collect($report['counts'])->map(fn ($count, $label) => [$label, $count])->values()->all());
    foreach ($report['checks'] as $label => $passed) {
        if ($passed) {
            $this->info('PASS: '.$label);
        } else {
            $this->error('FAIL: '.$label);
        }
    }

    return in_array(false, $report['checks'], true) ? 1 : 0;
})->purpose('Read-only verification of the fictional Cambodian school dataset');
```

## `resources/views/school/students/index.blade.php`

```php
@extends('school.layout')
@section('title', auth()->user()->role === 'parent' ? 'My children' : 'Students')
@section('content')
<div class="page-heading"><div><div class="eyebrow">OUR SCHOOL COMMUNITY</div><h1>{{ auth()->user()->role === 'parent' ? 'My children' : 'Students' }}</h1><p>Every student has a story. Help them write a great one.</p></div>@can('create', \App\Models\Student::class)<a href="{{ route('students.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Enroll student</a>@endcan</div>
<section class="panel"><div class="panel-header"><h2>Student directory <span class="badge text-bg-light ms-2">{{ $students->total() }}</span></h2></div><form method="get" class="row g-2 px-4 pb-4"><div class="col-md-5"><label for="search" class="visually-hidden">Search students</label><input id="search" type="search" name="search" class="form-control" placeholder="Search name, student ID, or email…" value="{{ request('search') }}"></div><div class="col-md-3"><label for="class" class="visually-hidden">Class</label><select id="class" name="class" class="form-select"><option value="">All classes</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(request('class') == $class->id)>{{ $class->name }} · {{ $class->section }}</option>@endforeach</select></div><div class="col-md-2"><label for="status" class="visually-hidden">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['active', 'inactive', 'graduated'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-outline-secondary w-100 h-100">Filter <i class="bi bi-funnel ms-1"></i></button></div><div class="col-md-3"><label class="form-label" for="sort">Sort by</label><select class="form-select" id="sort" name="sort">@foreach(['name' => 'Name', 'student_number' => 'Student ID', 'gender' => 'Gender', 'date_of_birth' => 'Date of birth', 'school_class_id' => 'Class', 'email' => 'Email', 'phone' => 'Phone', 'status' => 'Status'] as $key => $label)<option value="{{ $key }}" @selected(request('sort', 'name') === $key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label" for="direction">Order</label><select class="form-select" id="direction" name="direction"><option value="asc" @selected(request('direction', 'asc') === 'asc')>Ascending</option><option value="desc" @selected(request('direction') === 'desc')>Descending</option></select></div></form>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Student</th><th>Student ID</th><th>Gender</th><th>Age</th><th>Class</th><th>Email</th><th>Phone</th><th>Guardian</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($students as $student)<tr><td><div class="d-flex align-items-center">@if($student->photo)<img class="student-avatar" src="{{ asset('storage/'.$student->photo) }}" alt="">@else<span class="student-avatar">{{ mb_substr($student->name, 0, 1) }}</span>@endif<div><a href="{{ route('students.show', $student) }}"><strong>{{ $student->name }}</strong></a></div></div></td><td>{{ $student->student_number }}</td><td>{{ ucfirst($student->gender) }}</td><td>{{ $student->age }}</td><td>{{ $student->schoolClass->name }} · {{ $student->schoolClass->section }}</td><td>{{ $student->email }}</td><td>{{ $student->phone }}</td><td>{{ $student->guardian_name }}<small class="d-block mt-1">{{ $student->guardian_phone }}</small></td><td><span class="status-badge {{ $student->status }}">{{ $student->status }}</span></td><td><div class="d-flex align-items-center gap-2"><a class="btn btn-sm btn-light" href="{{ route('students.show', $student) }}" title="View student" aria-label="View {{ $student->name }}"><i class="bi bi-eye"></i></a>@can('update', $student)<a class="btn btn-sm btn-light" href="{{ route('students.edit', $student) }}" title="Edit student" aria-label="Edit {{ $student->name }}"><i class="bi bi-pencil"></i></a><form method="post" action="{{ route('students.destroy', $student) }}" data-confirm="Delete this student? Students with attendance, grades or fees are protected.">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" title="Delete student" aria-label="Delete {{ $student->name }}"><i class="bi bi-trash"></i></button></form>@endcan</div></td></tr>@empty<tr><td colspan="10"><div class="empty-state"><i class="bi bi-people"></i><strong>No students found</strong><p class="mt-2 mb-0">Try a different filter, or contact your school office to link your records.</p></div></td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $students->links() }}</div></section>
@endsection
```

## `resources/views/school/students/form.blade.php`

```php
@extends('school.layout')
@section('title', $student->exists ? 'Edit student' : 'Enroll student')
@section('content')
<div class="page-heading"><div><div class="eyebrow">STUDENT MANAGEMENT</div><h1>{{ $student->exists ? 'Edit student' : 'A new beginning.' }}</h1><p>Keep student details accurate, connected, and up to date.</p></div><a class="btn btn-outline-secondary" href="{{ route('students.index') }}"><i class="bi bi-arrow-left me-2"></i>Back to students</a></div>
<form method="post" enctype="multipart/form-data" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}">@csrf @if($student->exists) @method('PUT') @endif
<div class="row g-4"><div class="col-lg-8"><section class="panel p-4 mb-4"><h2 class="section-title"><i class="bi bi-person me-2 text-success"></i>Personal information</h2><div class="row g-3">
@foreach(['student_number' => ['Student ID', 'text'], 'name' => ['Full name', 'text'], 'email' => ['Email', 'email'], 'phone' => ['Phone number', 'tel'], 'date_of_birth' => ['Date of birth', 'date']] as $field => [$label, $type])<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} {{ $field !== 'phone' ? '*' : '' }}</label><input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $student->$field instanceof \DateTimeInterface ? $student->$field->format('Y-m-d') : $student->$field) }}" @required($field !== 'phone')>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach
<div class="col-md-6"><label class="form-label" for="gender">Gender *</label><select class="form-select" id="gender" name="gender" required><option value="">Select gender</option>@foreach(['female', 'male', 'other'] as $gender)<option value="{{ $gender }}" @selected(old('gender', $student->gender) === $gender)>{{ ucfirst($gender) }}</option>@endforeach</select></div><div class="col-12"><label class="form-label" for="address">Address</label><textarea class="form-control" rows="3" name="address" id="address">{{ old('address', $student->address) }}</textarea></div><div class="col-12"><label class="form-label" for="photo">Profile photo</label><input class="form-control" type="file" name="photo" id="photo" accept="image/png,image/jpeg,image/webp"><small class="text-secondary">JPG, PNG, or WebP. Maximum 2 MB.</small></div></div></section>
<section class="panel p-4"><h2 class="section-title"><i class="bi bi-people me-2 text-success"></i>Parent & guardian</h2><div class="row g-3">@foreach(['guardian_name' => 'Guardian name', 'guardian_phone' => 'Guardian phone'] as $field => $label)<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $student->$field) }}" required></div>@endforeach<div class="col-md-6"><label class="form-label" for="guardian_relationship">Relationship</label><select class="form-select" id="guardian_relationship" name="guardian_relationship"><option value="">Not recorded</option>@foreach(['father', 'mother', 'guardian'] as $relationship)<option value="{{ $relationship }}" @selected(old('guardian_relationship', $student->guardian_relationship) === $relationship)>{{ ucfirst($relationship) }}</option>@endforeach</select></div><div class="col-12"><label class="form-label" for="parent_id">Linked parent account</label><select class="form-select" name="parent_id" id="parent_id"><option value="">No parent account linked</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $student->parent_id) == $parent->id)>{{ $parent->name }} · {{ $parent->email }}</option>@endforeach</select><small class="text-secondary">Linked parents can view this child's records in their portal.</small></div></div></section></div>
<div class="col-lg-4"><section class="panel p-4"><h2 class="section-title"><i class="bi bi-building me-2 text-success"></i>School information</h2><div class="mb-3"><label class="form-label" for="school_class_id">Class *</label><select class="form-select" id="school_class_id" name="school_class_id" required><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id', $student->school_class_id) == $class->id)>{{ $class->name }} · {{ $class->section }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label" for="enrollment_date">Enrollment date *</label><input type="date" id="enrollment_date" name="enrollment_date" class="form-control" value="{{ old('enrollment_date', $student->enrollment_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></div><div class="mb-3"><label class="form-label" for="status">Status *</label><select class="form-select" name="status" id="status">@foreach(['active', 'inactive', 'graduated'] as $status)<option value="{{ $status }}" @selected(old('status', $student->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><div class="mb-4"><label class="form-label" for="user_id">Linked student account</label><select class="form-select" name="user_id" id="user_id"><option value="">No account linked</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('user_id', $student->user_id) == $account->id)>{{ $account->name }} · {{ $account->email }}</option>@endforeach</select></div><button class="btn btn-primary w-100"><i class="bi bi-check2 me-2"></i>{{ $student->exists ? 'Save changes' : 'Enroll student' }}</button><a href="{{ route('students.index') }}" class="btn btn-light w-100 mt-2">Cancel</a></section><div class="mt-3 px-2 text-secondary small"><i class="bi bi-shield-check me-1"></i>Student information is visible only to authorized school members and linked accounts.</div></div></div></form>
@endsection
```

## `resources/views/school/students/show.blade.php`

```php
@extends('school.layout')
@section('title', 'Student profile')
@section('content')
<div class="page-heading"><div><div class="eyebrow">STUDENT PROFILE · {{ $student->student_number }}</div><h1>{{ $student->name }}</h1><p>{{ $student->schoolClass->name }} · Section {{ $student->schoolClass->section }}</p></div><div class="d-flex gap-2"><a href="{{ route('students.index') }}" class="btn btn-outline-secondary">Back</a>@can('update', $student)<a class="btn btn-primary" href="{{ route('students.edit', $student) }}"><i class="bi bi-pencil me-2"></i>Edit profile</a>@endcan</div></div>
<div class="row g-4"><div class="col-lg-4"><section class="panel p-4"><div class="text-center border-bottom pb-4 mb-4">@if($student->photo)<img class="student-avatar" style="width:90px;height:90px" src="{{ asset('storage/'.$student->photo) }}" alt="{{ $student->name }}">@else<span class="student-avatar" style="width:90px;height:90px;font-size:30px">{{ mb_substr($student->name,0,1) }}</span>@endif<h2 class="h5 mt-3">{{ $student->name }}</h2><span class="status-badge {{ $student->status }}">{{ $student->status }}</span></div><dl class="profile-detail mb-0">@foreach(['email' => 'Email', 'phone' => 'Phone', 'gender' => 'Gender', 'address' => 'Address', 'guardian_name' => 'Guardian', 'guardian_phone' => 'Guardian phone', 'guardian_relationship' => 'Relationship'] as $field => $label)<dt>{{ $label }}</dt><dd>{{ $student->$field ?: '—' }}</dd>@endforeach<dt>Date of birth / age</dt><dd>{{ $student->date_of_birth->format('M d, Y') }} · {{ $student->date_of_birth->age }} years</dd><dt>Enrolled</dt><dd>{{ $student->enrollment_date->format('M d, Y') }}</dd></dl></section></div>
<div class="col-lg-8"><section class="panel mb-4"><div class="panel-header"><h2>Attendance history <small class="text-secondary fw-normal">· latest 30 records</small></h2><span class="small text-secondary">Attendance: {{ $student->attendance_percentage }}% (present + late / all records)</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead><tbody>@forelse($student->attendances as $attendance)<tr><td>{{ $attendance->date->format('M d, Y') }}</td><td><span class="status-badge {{ $attendance->status }}">{{ $attendance->status }}</span></td><td>{{ $attendance->notes ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="p-4 text-center">No attendance recorded yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel mb-4"><div class="panel-header"><h2>Examination results</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Examination</th><th>Subject</th><th>Marks</th><th>Percentage</th><th>Grade</th></tr></thead><tbody>@forelse($student->grades as $grade)<tr><td>{{ $grade->examination->name }}</td><td>{{ $grade->examination->subject->name }}</td><td>{{ $grade->marks }} / {{ $grade->examination->total_marks }}</td><td><strong>{{ round($grade->marks / $grade->examination->total_marks * 100, 1) }}%</strong></td><td>{{ $grade->letter_grade }}</td></tr>@empty<tr><td colspan="4" class="p-4 text-center">No results published yet.</td></tr>@endforelse</tbody></table></div></section>
@if(in_array(auth()->user()->role, ['administrator', 'parent']))<section class="panel"><div class="panel-header"><h2>School fees · USD</h2><span class="text-secondary small">Balance: ${{ number_format($student->fees->sum('amount') - $student->fees->sum('paid'), 2) }}</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Description</th><th>Amount</th><th>Paid</th><th>Due</th><th>Status</th><th>Balance</th></tr></thead><tbody>@forelse($student->fees as $fee)<tr><td>{{ $fee->description }}</td><td>${{ number_format($fee->amount,2) }}</td><td>${{ number_format($fee->paid,2) }}</td><td>{{ $fee->due_date->format('M d, Y') }}</td><td>{{ \Illuminate\Support\Str::headline($fee->payment_status) }}</td><td>${{ number_format($fee->balance, 2) }}</td></tr>@empty<tr><td colspan="4" class="p-4 text-center">No fees assigned.</td></tr>@endforelse</tbody></table></div></section>@endif</div></div>
@endsection
```

## `resources/views/school/modules/index.blade.php`

```php
@extends('school.layout')
@section('title', $meta['title'])
@section('content')
@php
    $canWrite = \App\Support\SchoolModules::canWrite(auth()->user(), $module);
    $columns = array_filter($meta['fields'], fn ($type) => $type !== 'password');
    $filters = isset($columns['status']) && is_array($columns['status']) ? $columns['status'] : (collect($columns)->first(fn ($type) => is_array($type)) ?? []);
    if ($module === 'fees') $filters = ['paid', 'pending', 'partially_paid', 'overdue'];
@endphp
<div class="page-heading"><div><div class="eyebrow">SCHOOL WORKSPACE</div><h1>{{ $meta['title'] }}</h1><p>Browse and manage your school records.</p></div>
@if($canWrite)<a href="{{ route('modules.create', $module) }}" class="btn btn-primary">Add record</a>@endif</div>
<section class="panel">
<div class="panel-header"><h2>{{ $meta['title'] }} <span class="badge text-bg-light">{{ $records->total() }}</span></h2></div>
<form class="d-flex flex-wrap gap-2 px-4 pb-3" method="get">
    <div><label for="search" class="form-label">Search</label><input class="form-control" id="search" type="search" name="search" value="{{ request('search') }}" placeholder="Search records"></div>
    @if($filters)<div><label for="filter" class="form-label">Filter</label><select class="form-select" id="filter" name="filter"><option value="">All</option>@foreach($filters as $filter)<option value="{{ $filter }}" @selected(request('filter') === $filter)>{{ \Illuminate\Support\Str::headline($filter) }}</option>@endforeach</select></div>@endif
    @if($module === 'classes')<div><label for="teacher" class="form-label">Teacher</label><select class="form-select" id="teacher" name="teacher"><option value="">All teachers</option>@foreach($options['teacher'] as $id => $name)<option value="{{ $id }}" @selected(request('teacher') == $id)>{{ $name }}</option>@endforeach</select></div>@endif
    @if($module === 'attendance')<div><label class="form-label" for="date">Date</label><input class="form-control" type="date" name="date" id="date" value="{{ request('date') }}"></div>@endif
    <div><label for="sort" class="form-label">Sort by</label><select class="form-select" name="sort" id="sort">@foreach(['id' => 'number'] + $columns as $field => $type)<option value="{{ $field }}" @selected(request('sort', 'id') === $field)>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</option>@endforeach</select></div>
    <div><label for="direction" class="form-label">Order</label><select class="form-select" id="direction" name="direction"><option value="desc" @selected(request('direction', 'desc') === 'desc')>Descending</option><option value="asc" @selected(request('direction') === 'asc')>Ascending</option></select></div>
    <div class="d-flex align-items-end gap-2"><button class="btn btn-outline-secondary">Apply</button><a class="btn btn-light" href="{{ route('modules.index', $module) }}">Reset</a></div>
</form>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th>
@foreach($columns as $field => $type)<th>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</th>@endforeach
@if($module === 'classes')<th>Students</th>@endif
@if($module === 'teachers')<th>Subjects</th>@endif
@if($module === 'parents')<th>Children</th>@endif
@if($module === 'fees')<th>Balance ? USD</th><th>Payment status</th>@endif
@if($module === 'grades')<th>Percentage</th><th>Grade</th>@endif
<th>Actions</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->id }}</td>
@foreach($columns as $field => $type)<td style="min-width:100px;max-width:350px">@include('school.modules.value')</td>@endforeach
@if($module === 'classes')<td>{{ $record->students_count }}</td>@endif
@if($module === 'teachers')<td>{{ $record->subjects->pluck('name')->unique()->join(', ') ?: 'Not assigned' }}</td>@endif
@if($module === 'parents')<td>{{ $record->children->pluck('name')->join(', ') ?: 'No children linked' }}</td>@endif
@if($module === 'fees')<td>${{ number_format($record->balance, 2) }}</td><td>{{ \Illuminate\Support\Str::headline($record->payment_status) }}</td>@endif
@if($module === 'grades')<td>{{ round($record->percentage, 1) }}%</td><td>{{ $record->letter_grade }}</td>@endif
<td><div class="d-flex gap-2"><a class="btn btn-sm btn-light" href="{{ route('modules.show', [$module, $record->id]) }}" aria-label="View record {{ $record->id }}"><i class="bi bi-eye"></i></a>
@if($canWrite)<a class="btn btn-sm btn-light" href="{{ route('modules.edit', [$module, $record->id]) }}" aria-label="Edit record {{ $record->id }}"><i class="bi bi-pencil"></i></a><form method="post" action="{{ route('modules.destroy', [$module, $record->id]) }}" data-confirm="Delete this record? Records with dependent school data are protected.">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Delete record {{ $record->id }}"><i class="bi bi-trash"></i></button></form>@endif
</div></td></tr>
@empty<tr><td colspan="{{ count($columns) + 5 }}"><div class="empty-state">No records found. Try another filter.</div></td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $records->links() }}</div></section>
@endsection
```

## `resources/views/school/modules/form.blade.php`

```php
@extends('school.layout')
@section('title', ($record->exists ? 'Edit · ' : 'Add · ').$meta['title'])
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $meta['title'] }}</div><h1>{{ $record->exists ? 'Update record' : 'Make a new addition.' }}</h1><p>Enter the details below. Your changes keep the whole school connected.</p></div><a class="btn btn-outline-secondary" href="{{ route('modules.index', $module) }}"><i class="bi bi-arrow-left me-2"></i>Back</a></div>
<section class="panel p-4" style="max-width:900px"><h2 class="section-title"><i class="bi bi-{{ $meta['icon'] }} text-success me-2"></i>{{ $meta['title'] }} details</h2><form method="post" action="{{ $record->exists ? route('modules.update', [$module, $record->id]) : route('modules.store', $module) }}">@csrf @if($record->exists) @method('PUT') @endif<div class="row g-3">
@foreach($meta['fields'] as $field => $type)
@php($value = old($field, $type === 'password' ? '' : ($record->$field instanceof \DateTimeInterface ? $record->$field->format('Y-m-d') : ($type === 'time' ? substr($record->$field ?? '',0,5) : $record->$field))))
<div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}"><label class="form-label" for="{{ $field }}">{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}@if($field === 'password' && $record->exists)<small class="fw-normal"> · leave blank to keep current</small>@endif</label>
@if(is_array($type) || (is_string($type) && isset($options[$type])))<select class="form-select @error($field) is-invalid @enderror" name="{{ $field }}" id="{{ $field }}"><option value="">Select an option</option>@foreach(is_array($type) ? array_combine($type, $type) : $options[$type] as $key => $label)<option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ ucfirst($label) }}</option>@endforeach</select>
@elseif($type === 'textarea')<textarea class="form-control @error($field) is-invalid @enderror" name="{{ $field }}" id="{{ $field }}" rows="5">{{ $value }}</textarea>
@else<input class="form-control @error($field) is-invalid @enderror" type="{{ $type }}" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}" @if($type === 'number') min="0" step="{{ in_array($field, ['marks','amount','paid','salary']) ? '0.01' : '1' }}" @endif @if($type === 'password') autocomplete="new-password" @endif>@endif
@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach</div>
@if($module === 'fees')<div class="alert alert-light border mt-4 small">Amounts are in USD. Enter the total paid and its recorded payment date. The monthly chart attributes that total to this date. This screen records fees; it does not process online payments.</div>@endif
@if($module === 'grades')<p class="text-secondary small mt-4">Choose an examination for the student's class. Marks cannot exceed the examination total.</p>@endif
<div class="border-top pt-4 mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2 me-2"></i>{{ $record->exists ? 'Save changes' : 'Create record' }}</button><a class="btn btn-light" href="{{ route('modules.index', $module) }}">Cancel</a></div></form></section>
@endsection
```

## `resources/views/school/modules/show.blade.php`

```php
@extends('school.layout')
@section('title', $meta['title'].' details')
@section('content')
<div class="page-heading"><div><h1>{{ $meta['title'] }} ? #{{ $record->id }}</h1><p>School record details</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('modules.index', $module) }}">Back</a>@if(\App\Support\SchoolModules::canWrite(auth()->user(), $module))<a class="btn btn-primary" href="{{ route('modules.edit', [$module, $record->id]) }}">Edit</a>@endif</div></div>
<section class="panel p-4"><dl class="profile-detail">
@foreach($meta['fields'] as $field => $type)
@if($type !== 'password')<dt>{{ \Illuminate\Support\Str::headline(str_replace('_id', '', $field)) }}</dt><dd>@include('school.modules.value')</dd>@endif
@endforeach
@if($module === 'fees')<dt>Balance ? USD</dt><dd>${{ number_format($record->balance, 2) }}</dd><dt>Payment status</dt><dd>{{ \Illuminate\Support\Str::headline($record->payment_status) }}</dd>@endif
@if($module === 'grades')<dt>Result</dt><dd>{{ round($record->percentage, 1) }}% ? {{ $record->letter_grade }}</dd>@endif
@if($module === 'teachers')<dt>Subjects</dt><dd>{{ $record->subjects->map(fn ($subject) => $subject->name.' ('.$subject->schoolClass->name.$subject->schoolClass->section.')')->join(', ') ?: 'Not assigned' }}</dd>@endif
@if($module === 'parents')<dt>Children</dt><dd>@foreach($record->children as $child)<a class="d-block" href="{{ route('students.show', $child) }}">{{ $child->name }} ? {{ $child->student_number }} ? {{ ucfirst($child->guardian_relationship ?? 'guardian') }}</a>@endforeach</dd>@endif
@if($module === 'classes')<dt>Students</dt><dd>{{ $record->students()->count() }}</dd>@endif
@if($module === 'announcements')<dt>Created by</dt><dd>{{ $record->creator?->name ?? 'Not recorded' }}</dd>@endif
</dl></section>
@endsection
```

## `resources/views/school/modules/value.blade.php`

```php
@if(is_string($type) && isset($options[$type]))
    {{ $options[$type][$record->$field] ?? '?' }}
@elseif($record->$field instanceof \DateTimeInterface)
    {{ $record->$field->format('M d, Y') }}
@elseif(in_array($field, ['status', 'role']))
    <span class="status-badge {{ $record->$field }}">{{ $record->$field }}</span>
@elseif(in_array($field, ['amount', 'paid', 'salary']))
    {{ $record->$field === null ? '?' : '$'.number_format($record->$field, 2) }}
@else
    {{ $record->$field ?? '?' }}
@endif
```

## `resources/views/school/dashboard.blade.php`

```php
@extends('school.layout')
@section('title', 'Overview')
@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
    <span id="dashboard-sync-status" class="small text-secondary" role="status" aria-live="polite">Live data ? refreshes every 30 seconds</span>
    <button type="button" id="dashboard-refresh" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
</div>
<div id="dashboard-content" data-url="{{ route('dashboard.data') }}" aria-busy="false">
    @include('school.partials.dashboard-content')
</div>
@php($dashboardInitial = ['charts' => $charts, 'showRevenue' => in_array(auth()->user()->role, ['administrator', 'parent'])])
<script type="application/json" id="dashboard-initial-data">@json($dashboardInitial)</script>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}" defer></script>
@endpush
```

## `tests/Feature/CambodianSchoolDataTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SampleSchoolVerifier;
use App\Support\SchoolModules;
use Database\Seeders\CambodianSchoolSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambodianSchoolDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_dataset_is_valid_preserves_existing_data_and_is_repeatable(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
        $this->seed(SchoolSeeder::class);
        $existing = DB::table('students')->orderBy('id')->get()->map(fn ($s) => (array) $s)->all();
        $this->seed(CambodianSchoolSeeder::class);
        $report = SampleSchoolVerifier::report();
        foreach ($report['checks'] as $label => $passed) {
            $this->assertTrue($passed, $label);
        }
        $tables = ['users', 'school_classes', 'students', 'subjects', 'attendances', 'examinations', 'grades', 'fees', 'schedules', 'announcements'];
        $snapshot = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        Student::where('student_number', 'STU001')->update(['phone' => '012999999']);
        $snapshot['students'] = DB::table('students')->orderBy('id')->get()->toJson();
        $this->seed(CambodianSchoolSeeder::class);
        foreach ($tables as $table) {
            $this->assertSame($snapshot[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table.' changed on a repeated seed');
        }
        foreach ($existing as $row) {
            $this->assertSame($row, (array) DB::table('students')->find($row['id']));
        }
        $this->artisan('school:verify-sample')->assertSuccessful();
        $admin = User::where('role', 'administrator')->firstOrFail();
        $this->actingAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee('Absent students today');
        foreach (SchoolModules::all() as $module => $meta) {
            $this->get('/school/'.$module.'?sort=name&direction=asc')->assertOk();
            $record = SchoolModules::query($module, $admin)->first();
            if ($record) {
                $this->get('/school/'.$module.'/'.$record->id)->assertOk();
            }
        }
        $this->get('/school/fees?search=STU001&filter=partially_paid')->assertOk()->assertViewHas('records', fn ($records) => $records->count() > 0 && $records->every(fn ($fee) => $fee->payment_status === 'partially_paid' && $fee->student->student_number === 'STU001'));
        $this->get('/students?search=STU001&sort=student_number&direction=asc')->assertOk()->assertSee('STU001');
        $student = Student::where('student_number', 'STU001')->firstOrFail();
        $this->get('/students/'.$student->id)->assertOk()->assertSee('Attendance:');
        $this->delete('/students/'.$student->id)->assertSessionHasErrors('record');
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->delete('/school/parents/'.$student->parent_id)->assertSessionHasErrors('record');
        $this->actingAs($student->parent);
        $this->get('/students')->assertOk()->assertSee($student->name);
        $this->get('/school/parents')->assertForbidden();
    }

    public function test_sample_identifier_collision_rolls_back_without_modifying_existing_records(): void
    {
        $class = SchoolClass::create(['name' => 'Existing class', 'section' => 'A']);
        Student::factory()->create(['student_number' => 'STU001', 'school_class_id' => $class->id, 'email' => 'real@example.com']);
        try {
            $this->seed(CambodianSchoolSeeder::class);
            $this->fail('Expected a sample identifier collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('collision', $exception->getMessage());
        }
        $this->assertSame(1, Student::count());
        $this->assertSame(1, SchoolClass::count());
        $this->assertSame(0, User::count());
    }

    public function test_grading_boundaries_payment_status_and_attendance_percentage(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::create(['name' => 'Math', 'code' => 'T-MATH', 'school_class_id' => $student->school_class_id]);
        $exam = Examination::create(['name' => 'Test', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        foreach ([100 => 'A', 90 => 'A', 89 => 'B', 80 => 'B', 79 => 'C', 70 => 'C', 69 => 'D', 60 => 'D', 59 => 'F', 0 => 'F'] as $marks => $letter) {
            $grade = new Grade(['marks' => $marks]);
            $grade->setRelation('examination', $exam);
            $this->assertSame($letter, $grade->letter_grade);
        }
        foreach (['present', 'late', 'absent', 'excused'] as $offset => $status) {
            Attendance::create(['student_id' => $student->id, 'date' => today()->subDays($offset), 'status' => $status]);
        }
        $this->assertSame(50.0, $student->attendance_percentage);
        $fee = new Fee(['amount' => 100, 'paid' => 40, 'due_date' => today()->addDay()]);
        $this->assertSame('60.00', $fee->balance);
        $this->assertSame('partially_paid', $fee->payment_status);
        $fee->due_date = today()->subDay();
        $this->assertSame('overdue', $fee->payment_status);
    }

    public function test_timetable_rejects_teacher_room_and_class_overlaps_and_outside_hours(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $teacher = User::factory()->create();
        $teacher->role = 'teacher';
        $teacher->save();
        $one = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A']);
        $two = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A']);
        $first = Subject::create(['name' => 'Math', 'code' => 'MATH-A', 'school_class_id' => $one->id, 'teacher_id' => $teacher->id]);
        $second = Subject::create(['name' => 'Math', 'code' => 'MATH-B', 'school_class_id' => $two->id, 'teacher_id' => $teacher->id]);
        Schedule::create(['subject_id' => $first->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'Room A']);
        $this->actingAs($admin);
        $data = ['subject_id' => $second->id, 'day' => 'Monday', 'starts_at' => '07:30', 'ends_at' => '08:30', 'room' => 'Room B'];
        $this->post('/school/schedules', $data)->assertSessionHasErrors('starts_at');
        $this->post('/school/schedules', array_replace($data, ['starts_at' => '11:00', 'ends_at' => '12:00']))->assertSessionHasErrors('starts_at');
        $this->post('/school/schedules', array_replace($data, ['starts_at' => '08:00', 'ends_at' => '09:00']))->assertSessionHasNoErrors();
    }
}
```

## `tests/Feature/SchoolTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolModules;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function student(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'student_number' => 'ST-'.fake()->unique()->numerify('#####'), 'name' => 'Test Student',
            'email' => fake()->unique()->safeEmail(), 'gender' => 'female', 'date_of_birth' => '2013-04-12',
            'school_class_id' => SchoolClass::create(['name' => fake()->unique()->word(), 'section' => 'A', 'capacity' => 30])->id,
            'guardian_name' => 'Test Guardian', 'guardian_phone' => '555-0100', 'enrollment_date' => today()->toDateString(), 'status' => 'active',
        ], $attributes));
    }

    public function test_guests_are_redirected_and_auth_pages_render(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('A good day to learn.');
        $this->get('/register')->assertOk();
    }

    public function test_registration_cannot_escalate_roles_and_login_logout_work(): void
    {
        $this->post('/register', ['name' => 'New Student', 'email' => 'new@example.com', 'password' => 'SecurePass123', 'password_confirmation' => 'SecurePass123', 'role' => 'administrator'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertSame('student', User::where('email', 'new@example.com')->first()->role);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'SecurePass123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_demo_dashboards_and_every_authorized_module_render_for_all_roles(): void
    {
        $this->seed(SchoolSeeder::class);
        foreach (['admin', 'teacher', 'student', 'parent'] as $name) {
            $user = User::where('email', $name.'@school.test')->firstOrFail();
            $this->actingAs($user);
            $this->get('/dashboard')->assertOk()->assertSee('Welcome back');
            $this->get('/students')->assertOk();
            foreach (SchoolModules::all() as $module => $meta) {
                $this->get('/school/'.$module)->assertStatus(SchoolModules::canView($user, $module) ? 200 : 403);
                $this->get('/school/'.$module.'/create')->assertStatus(SchoolModules::canWrite($user, $module) ? 200 : 403);
                if (SchoolModules::canWrite($user, $module)) {
                    $record = SchoolModules::query($module, $user)->first();
                    if ($record) {
                        $this->get('/school/'.$module.'/'.$record->id.'/edit')->assertOk();
                    }
                }
            }
        }
        $this->actingAs(User::where('role', 'administrator')->first())->get('/reports')->assertOk();
        $this->get('/students/create')->assertOk();
        $this->get('/students/'.Student::first()->id.'/edit')->assertOk();
        $this->get('/students/'.Student::first()->id)->assertOk()->assertSee('Examination results');
    }

    public function test_administrator_can_enroll_update_search_and_delete_student_with_photo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->account('administrator'));
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'B']);
        $data = ['student_number' => 'ST-100', 'name' => 'New Student', 'gender' => 'male', 'date_of_birth' => '2012-01-01', 'email' => 'enrolled@example.com', 'school_class_id' => $class->id, 'guardian_name' => 'Guardian', 'guardian_phone' => '12345', 'enrollment_date' => today()->toDateString(), 'status' => 'active'];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->post('/students', $data + ['photo' => UploadedFile::fake()->createWithContent('photo.png', $png)])->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::firstOrFail();
        $path = $student->photo;
        Storage::disk('public')->assertExists($path);
        $this->put('/students/'.$student->id, array_merge($data, ['name' => 'Updated Student', 'photo' => null]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($path, $student->fresh()->photo);
        $this->get('/students?search=Updated')->assertSee('Updated Student');
        $this->post('/students', $data)->assertSessionHasErrors(['student_number', 'email']);
        $this->delete('/students/'.$student->id)->assertRedirect('/students');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_parent_and_student_cannot_access_unrelated_records_or_modify_students(): void
    {
        $parent = $this->account('parent');
        $studentUser = $this->account('student');
        $own = $this->student(['parent_id' => $parent->id, 'user_id' => $studentUser->id, 'name' => 'Linked Child']);
        $other = $this->student(['name' => 'Private Child']);
        foreach ([$parent, $studentUser] as $user) {
            $this->actingAs($user)->get('/students')->assertSee('Linked Child')->assertDontSee('Private Child');
            $this->get('/students/'.$own->id)->assertOk();
            $this->get('/students/'.$other->id)->assertForbidden();
            $this->get('/students/create')->assertForbidden();
            $this->post('/students', [])->assertForbidden();
            $this->put('/students/'.$own->id, [])->assertForbidden();
            $this->delete('/students/'.$own->id)->assertForbidden();
            $this->get('/reports')->assertForbidden();
        }
        $this->actingAs($parent);
        $fee = Fee::create(['student_id' => $other->id, 'description' => 'Private tuition', 'amount' => 100, 'paid' => 0, 'due_date' => today()]);
        $this->get('/school/fees')->assertDontSee('Private tuition');
        $this->put('/school/fees/'.$fee->id, [])->assertNotFound();
    }

    public function test_teacher_can_manage_only_assigned_attendance_and_subject_grades(): void
    {
        $teacher = $this->account('teacher');
        $otherTeacher = $this->account('teacher');
        $own = $this->student();
        $own->schoolClass->update(['teacher_id' => $teacher->id]);
        $other = $this->student();
        $subject = Subject::create(['name' => 'Math', 'code' => 'M1', 'school_class_id' => $own->school_class_id, 'teacher_id' => $teacher->id]);
        $exam = Examination::create(['name' => 'Midterm', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        $this->actingAs($teacher)->post('/school/attendance', ['student_id' => $own->id, 'date' => today()->toDateString(), 'status' => 'present'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/school/attendance', ['student_id' => $other->id, 'date' => today()->toDateString(), 'status' => 'present'])->assertSessionHasErrors('student_id');
        $this->post('/school/grades', ['student_id' => $own->id, 'examination_id' => $exam->id, 'marks' => 101])->assertSessionHasErrors('marks');
        $this->post('/school/grades', ['student_id' => $own->id, 'examination_id' => $exam->id, 'marks' => 88])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/school/grades', ['student_id' => $other->id, 'examination_id' => $exam->id, 'marks' => 88])->assertSessionHasErrors('student_id');
        $attendance = Attendance::first();
        $this->actingAs($otherTeacher)->put('/school/attendance/'.$attendance->id, [])->assertNotFound();
        $this->actingAs($teacher)->post('/school/users', [])->assertForbidden();
    }

    public function test_fee_overpayment_and_duplicate_attendance_are_rejected(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $this->post('/school/fees', ['student_id' => $student->id, 'description' => 'Tuition', 'amount' => 100, 'paid' => 101, 'due_date' => today()->toDateString()])->assertSessionHasErrors('paid');
        $data = ['student_id' => $student->id, 'date' => today()->toDateString(), 'status' => 'present'];
        $this->post('/school/attendance', $data)->assertSessionHasNoErrors();
        $this->post('/school/attendance', $data)->assertSessionHasErrors('date');
    }

    public function test_user_roles_can_be_assigned_only_by_admin_and_self_lockout_is_blocked(): void
    {
        $admin = $this->account('administrator');
        $this->actingAs($admin);
        $this->post('/school/users', ['name' => 'Parent Account', 'email' => 'parent@example.com', 'password' => 'SecurePass123', 'role' => 'parent'])->assertSessionHasNoErrors();
        $this->assertSame('parent', User::where('email', 'parent@example.com')->first()->role);
        $this->put('/school/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'student'])->assertSessionHasErrors('role');
        $this->delete('/school/users/'.$admin->id)->assertSessionHasErrors('record');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'administrator']);
    }

    public function test_administrator_crud_for_all_school_modules(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $teacher = $this->account('teacher');
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'TEST-MATH', 'school_class_id' => $student->school_class_id, 'teacher_id' => $teacher->id]);
        $exam = Examination::create(['name' => 'Test exam', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        $cases = [
            'parents' => ['name' => 'New Guardian', 'email' => 'newguardian@example.com', 'password' => 'SecurePass123'],
            'teachers' => ['name' => 'New Teacher', 'email' => 'newteacher@example.com', 'password' => 'SecurePass123'],
            'classes' => ['name' => 'New Class', 'section' => 'B', 'capacity' => 35, 'room' => 'Room 2', 'teacher_id' => $teacher->id],
            'subjects' => ['name' => 'New Subject', 'code' => 'NEW-SUB', 'school_class_id' => $student->school_class_id, 'teacher_id' => $teacher->id],
            'attendance' => ['student_id' => $student->id, 'date' => today()->toDateString(), 'status' => 'present'],
            'examinations' => ['name' => 'New Exam', 'subject_id' => $subject->id, 'date' => today()->toDateString(), 'total_marks' => 100],
            'grades' => ['student_id' => $student->id, 'examination_id' => $exam->id, 'marks' => 95],
            'fees' => ['student_id' => $student->id, 'description' => 'Term fee', 'amount' => 200, 'paid' => 50, 'paid_at' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString()],
            'assignments' => ['subject_id' => $subject->id, 'title' => 'Practice', 'description' => 'Complete the exercises.', 'due_date' => today()->addDays(7)->toDateString()],
            'schedules' => ['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => 'Room 1'],
            'announcements' => ['title' => 'Welcome', 'body' => 'Welcome to school.', 'audience' => 'all', 'published_at' => today()->toDateString()],
            'users' => ['name' => 'New Parent', 'email' => 'newparent@example.com', 'password' => 'SecurePass123', 'role' => 'parent'],
        ];
        foreach ($cases as $module => $data) {
            $model = SchoolModules::get($module)['model'];
            $this->post('/school/'.$module, $data)->assertSessionHasNoErrors()->assertRedirect('/school/'.$module);
            $record = $model::latest('id')->firstOrFail();
            $this->get('/school/'.$module.'/'.$record->id.'/edit')->assertOk();
            $updated = match ($module) {
                'attendance' => ['status' => 'late'], 'grades' => ['marks' => 90], 'fees' => ['paid' => 100],
                'assignments', 'announcements' => ['title' => 'Updated title'], 'schedules' => ['room' => 'Room 9'],
                default => ['name' => 'Updated name'],
            };
            $this->put('/school/'.$module.'/'.$record->id, array_merge($data, $updated))->assertSessionHasNoErrors()->assertRedirect('/school/'.$module);
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id] + $updated);
            $this->delete('/school/'.$module.'/'.$record->id)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
        }
    }

    public function test_published_result_constraints_survive_examination_edits(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $subject = Subject::create(['name' => 'Math', 'code' => 'MATH-1', 'school_class_id' => $student->school_class_id]);
        $exam = Examination::create(['name' => 'Assessment', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        Grade::create(['student_id' => $student->id, 'examination_id' => $exam->id, 'marks' => 95]);
        $this->put('/school/examinations/'.$exam->id, ['name' => 'Assessment', 'subject_id' => $subject->id, 'date' => today()->toDateString(), 'total_marks' => 90])->assertSessionHasErrors('total_marks');
    }
}
```

## `resources/views/school/partials/dashboard-content.blade.php`

```php
@php
    $role = auth()->user()->role;
    $studentLabel = match ($role) { 'administrator' => 'Total students', 'teacher' => 'Students in my classes', 'parent' => 'My children', default => 'My student profile' };
    $scope = match ($role) { 'administrator' => 'School-wide totals from the current database.', 'teacher' => 'These figures cover your assigned classes and subjects.', 'parent' => 'These figures cover your linked children and their classes.', default => 'These figures cover your linked student profile and class.' };
@endphp
<p class="text-secondary mb-3">{{ $scope }}</p>
@if($role !== 'administrator' && $studentCount === 0)
<div class="alert alert-info" role="status">No student records are linked to your account{{ $role === 'teacher' ? ' through a class or teaching assignment' : '' }}. Ask the school administrator to link your {{ $role === 'parent' ? 'children' : ($role === 'teacher' ? 'teaching assignments' : 'student profile') }}. These zero counts do not mean the school database is empty.</div>
@endif
<div class="page-heading"><div><div class="eyebrow">YOUR SCHOOL, AT A GLANCE</div><h1>Welcome back, {{ explode(' ', auth()->user()->name)[0] }} <span style="font-size:22px">☀️</span></h1><p>Here’s what’s happening at Greenfield Academy today.</p></div><div class="date-pill"><i class="bi bi-calendar3 me-2"></i>{{ now()->format('l, M d, Y') }}</div></div>
<div class="row g-3">
    @foreach([[$studentLabel, $studentCount, 'people', 'green', 'Enrolled in your school community'], [$role === 'administrator' ? 'Total teachers' : 'My teachers', $teacherCount, 'person-workspace', 'blue', 'Inspiring the next generation'], [$role === 'administrator' ? 'Total classes' : 'My classes', $classCount, 'building', 'orange', 'Spaces for growing minds'], [$role === 'administrator' ? 'Total subjects' : 'My subjects', $subjectCount, 'book', 'purple', 'A world of learning opportunities']] as [$label, $value, $icon, $color, $note])
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><span>{{ $label }}</span><span class="stat-icon tint-{{ $color }}"><i class="bi bi-{{ $icon }}"></i></span></div><div class="stat-value">{{ number_format($value) }}</div><div class="stat-note"><i class="bi bi-arrow-up-right"></i>{{ $note }}</div></div></div>
    @endforeach
</div>
<div class="mini-stats">
    <div class="mini-stat"><i class="bi bi-calendar2-check"></i><div><small>Present students today</small><strong>{{ $presentCount }} <span class="fw-normal text-secondary" style="font-size:10px">/ {{ $attendanceCount }} recorded</span></strong></div></div>
    @if(in_array(auth()->user()->role, ['administrator', 'parent']))
    <div class="mini-stat"><i class="bi bi-cash-stack"></i><div><small>Fees collected · USD</small><strong>${{ number_format($collected, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-hourglass-split"></i><div><small>Pending payments · USD</small><strong>${{ number_format($pending, 2) }}</strong></div></div>
    @else
    <div class="mini-stat"><i class="bi bi-journal-text"></i><div><small>Learning resources</small><a href="{{ route('modules.index', 'assignments') }}">View assignments <i class="bi bi-arrow-up-right"></i></a></div></div>
    <div class="mini-stat"><i class="bi bi-clock"></i><div><small>Your school week</small><a href="{{ route('modules.index', 'schedules') }}">View timetable <i class="bi bi-arrow-up-right"></i></a></div></div>
    @endif
    <div class="mini-stat"><i class="bi bi-person-dash"></i><div><small>Absent students today</small><strong>{{ $absentCount }}</strong></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>Student enrollment</h2><p>A growing community of bright minds</p></div><span class="date-pill py-1">Last 6 months <i class="bi bi-chevron-down ms-2"></i></span></div><div class="chart-wrap"><canvas id="enrollmentChart" role="img" aria-label="Monthly student enrollment chart"></canvas></div></section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>Attendance today</h2><p>Every school day matters</p></div><span class="stat-icon tint-green"><i class="bi bi-check2-circle"></i></span></div><div class="chart-wrap small"><canvas id="attendanceChart" role="img" aria-label="Today's attendance by status"></canvas></div><div class="chart-legend"><span><b>{{ $charts['attendance'][0] }}</b> Present</span><span><b>{{ $charts['attendance'][1] }}</b> Absent</span><span><b>{{ $charts['attendance'][2] }}</b> Late</span></div></section></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Monthly revenue' : 'Enrollment trend' }}</h2><p>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Recorded fee payments · USD' : 'New students joining your classes' }}</p></div><div class="chart-key"><span>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Collected fees' : 'New students' }}</span></div></div><div class="chart-wrap"><canvas id="revenueChart" role="img" aria-label="Monthly trend chart"></canvas></div></section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>Students by gender</h2><p>Different perspectives. Shared possibilities.</p></div></div><div class="chart-wrap small"><canvas id="genderChart" role="img" aria-label="Student distribution by gender"></canvas></div><div class="chart-legend"><span><b>{{ $charts['gender'][0] }}</b> Female</span><span><b>{{ $charts['gender'][1] }}</b> Male</span><span><b>{{ $charts['gender'][2] }}</b> Other</span></div></section></div>
</div>
<div class="row g-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>Recent registrations</h2><p>The newest faces in our community</p></div><a class="text-link" href="{{ route('students.index') }}">View all students <i class="bi bi-arrow-right ms-1"></i></a></div><div class="table-responsive"><table class="table"><thead><tr><th>Student name</th><th>Student ID</th><th>Class</th><th>Enrolled</th><th>Status</th></tr></thead><tbody>@forelse($recent as $student)<tr><td><a href="{{ route('students.show', $student) }}"><span class="student-avatar">{{ mb_substr($student->name,0,1) }}</span><strong>{{ $student->name }}</strong></a></td><td>{{ $student->student_number }}</td><td>{{ $student->schoolClass->name }} · {{ $student->schoolClass->section }}</td><td>{{ $student->enrollment_date->format('M d, Y') }}</td><td><span class="status-badge {{ $student->status }}">{{ $student->status }}</span></td></tr>@empty<tr><td colspan="5" class="empty-state">No student records yet. {{ auth()->user()->role === 'administrator' ? 'Enroll your first student to get started.' : 'Ask the school office to link your account.' }}</td></tr>@endforelse</tbody></table></div>
    @if(auth()->user()->role === 'administrator')<div class="p-3 border-top"><a href="{{ route('students.create') }}" class="text-link"><i class="bi bi-plus-lg me-1"></i> Enroll a new student</a></div>@endif</section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>School announcements</h2><p>Stay in the loop</p></div><a href="{{ route('modules.index', 'announcements') }}" aria-label="View announcements"><i class="bi bi-arrow-up-right"></i></a></div><div class="panel-body pt-0">@forelse($announcements as $announcement)<article class="announcement"><span class="announcement-icon"><i class="bi bi-megaphone"></i></span><div><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</p><small><i class="bi bi-clock me-1"></i>{{ $announcement->published_at->format('M d, Y') }}</small></div></article>@empty<div class="empty-state py-4">You're all caught up. No announcements yet.</div>@endforelse</div></section></div>
</div>
```

## `public/js/dashboard.js`

```javascript
(() => {
    const content = document.getElementById('dashboard-content');
    const button = document.getElementById('dashboard-refresh');
    const status = document.getElementById('dashboard-sync-status');
    if (!content || !button || !status) return;

    let charts = [];
    let pending = false;
    let sessionExpired = false;

    function renderCharts(payload) {
        if (typeof Chart === 'undefined') return;
        const data = payload.charts;
        Chart.defaults.font.family = 'DM Sans, sans-serif';
        Chart.defaults.font.size = 10;
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#98a4ab';
        const axes = () => ({x: {grid: {display: false}, border: {display: false}}, y: {beginAtZero: true, grid: {color: '#83909825'}, border: {display: false}, ticks: {maxTicksLimit: 5, precision: 0}}});
        const create = (id, config) => {
            const canvas = document.getElementById(id);
            if (canvas) charts.push(new Chart(canvas, config));
        };
        const options = {maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}};
        create('enrollmentChart', {type: 'line', data: {labels: data.labels, datasets: [{label: 'New students', data: data.enrollment, borderColor: '#2d9475', backgroundColor: '#249a7410', fill: true, borderWidth: 2.5, tension: .4, pointRadius: 3}]}, options: {...options, scales: axes()}});
        const doughnut = (id, labels, values, colors) => create(id, {type: 'doughnut', data: {labels, datasets: [{data: values, backgroundColor: colors, borderWidth: 0, borderRadius: 5}]}, options: {...options, cutout: '77%'}});
        doughnut('attendanceChart', ['Present', 'Absent', 'Late', 'Excused'], data.attendance, ['#329c7c', '#efb8a2', '#f2d28b', '#a4b4d9']);
        create('revenueChart', {type: 'bar', data: {labels: data.labels, datasets: [{label: payload.showRevenue ? 'Collected (USD)' : 'New students', data: payload.showRevenue ? data.revenue : data.enrollment, backgroundColor: ['#d9eee4', '#d9eee4', '#d9eee4', '#d9eee4', '#d9eee4', '#329c7c'], borderRadius: 5, maxBarThickness: 32}]}, options: {...options, scales: axes()}});
        doughnut('genderChart', ['Female', 'Male', 'Other'], data.gender, ['#a6b9e8', '#55a38c', '#e8cc91']);
    }

    async function refresh() {
        if (pending || document.hidden || sessionExpired) return;
        pending = true;
        button.disabled = true;
        content.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating dashboard…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(content.dataset.url, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            if (response.status === 401 || response.status === 403 || response.redirected) {
                sessionExpired = true;
                status.textContent = 'Your session has ended. Sign in again to refresh.';
                return;
            }
            if (!response.ok) throw new Error('Dashboard request failed');
            const payload = await response.json();
            if (typeof payload.html !== 'string' || !payload.charts) throw new Error('Invalid dashboard response');
            charts.forEach(chart => chart.destroy());
            charts = [];
            content.innerHTML = payload.html;
            renderCharts(payload);
            status.textContent = `Updated ${new Date().toLocaleTimeString()} · refreshes every 30 seconds`;
        } catch (_) {
            status.textContent = 'Could not refresh. Showing the last loaded data. Try Refresh again.';
        } finally {
            clearTimeout(timeout);
            pending = false;
            button.disabled = sessionExpired;
            content.setAttribute('aria-busy', 'false');
        }
    }

    renderCharts(JSON.parse(document.getElementById('dashboard-initial-data').textContent));
    button.addEventListener('click', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    window.addEventListener('focus', refresh);
    setInterval(refresh, 30000);
    refresh();
})();
```

## `tests/Feature/DashboardAjaxTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_totals_and_charts_follow_current_database_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
        $this->seed(SchoolSeeder::class);
        $this->actingAs(User::where('role', 'administrator')->firstOrFail());
        $this->get('/dashboard')->assertOk()->assertSee('dashboard-refresh')->assertSee('School-wide totals');
        $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.studentCount', Student::count())
            ->assertJsonPath('summary.teacherCount', User::where('role', 'teacher')->count())
            ->assertJsonPath('summary.classCount', SchoolClass::count())
            ->assertJsonPath('summary.subjectCount', Subject::count());
        $before = Student::count();
        $student = Student::factory()->create(['gender' => 'female', 'enrollment_date' => today()]);
        Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'absent']);
        $fee = Fee::create(['student_id' => $student->id, 'description' => 'AJAX test', 'amount' => 100, 'paid' => 25, 'due_date' => today()->addWeek(), 'paid_at' => today()]);
        $response = $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.studentCount', $before + 1)
            ->assertJsonPath('summary.absentCount', Attendance::whereDate('date', today())->where('status', 'absent')->count())
            ->assertJsonPath('charts.gender.0', Student::where('gender', 'female')->count());
        $this->assertSame((float) Fee::sum('paid'), (float) $response->json('summary.collected'));
        $this->assertStringContainsString($student->name, $response->json('html'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $fee->update(['paid' => 100]);
        $response = $this->getJson('/dashboard/data')->assertOk();
        $this->assertSame((float) Fee::sum('paid'), (float) $response->json('summary.collected'));
        $this->assertSame((float) Fee::sum('amount') - (float) Fee::sum('paid'), (float) $response->json('summary.pending'));
        $student->delete();
        $this->getJson('/dashboard/data')->assertJsonPath('summary.studentCount', $before);
    }

    public function test_ajax_is_authenticated_scoped_and_explains_unlinked_accounts(): void
    {
        $this->getJson('/dashboard/data')->assertUnauthorized();
        $this->seed(SchoolSeeder::class);
        $parent = User::where('email', 'parent@school.test')->firstOrFail();
        $this->actingAs($parent)->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 1);
        $student = User::where('email', 'student@school.test')->firstOrFail();
        $this->actingAs($student)->getJson('/dashboard/data?role=administrator')->assertOk()
            ->assertJsonPath('summary.studentCount', 1)->assertJsonPath('summary.collected', null)
            ->assertJsonPath('summary.pending', null)->assertJsonPath('charts.revenue', []);
        $unlinked = User::factory()->create();
        $response = $this->actingAs($unlinked)->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 0);
        $this->assertStringContainsString('No student records are linked', $response->json('html'));
        $this->assertStringNotContainsString('Emma Parker', $response->json('html'));
        $child = Student::where('email', 'student@school.test')->firstOrFail();
        $child->update(['user_id' => $unlinked->id]);
        $response = $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 1);
        $this->assertStringNotContainsString('No student records are linked', $response->json('html'));
    }
}
```
