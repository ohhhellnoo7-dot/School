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
