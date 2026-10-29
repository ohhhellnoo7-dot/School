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
                    foreach (['07:00', '08:00', '09:00', '10:00', '13:00', '14:00', '15:00', '16:30', '17:30'] as $time) {
                        $start = $time;
                        $end = \Carbon\Carbon::parse($time)->addHour()->format('H:i');
                        $room = $subject->schoolClass->room ?: 'KH-Class-'.$subject->school_class_id;
                        if (! \App\Support\TeacherTimetable::withinHours($subject, $start, $end) || TimetableConflicts::exists($subject, $day, $start, $end, $room)) {
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
