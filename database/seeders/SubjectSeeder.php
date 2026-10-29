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
