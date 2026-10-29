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
