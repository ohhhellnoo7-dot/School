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
