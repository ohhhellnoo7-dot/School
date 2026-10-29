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
            ->where(fn ($query) => $query->whereNull('program')->orWhere('program', 'Khmer'))
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
