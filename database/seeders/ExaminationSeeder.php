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
