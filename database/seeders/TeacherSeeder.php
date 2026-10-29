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
