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
