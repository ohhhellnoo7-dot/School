<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramClassSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $teachers = User::where('role', 'teacher')->orderBy('id')->get()->groupBy('program');
            foreach (['Khmer', 'English', 'Chinese'] as $program) {
                if (($teachers->get($program)?->count() ?? 0) === 0) {
                    throw new \RuntimeException('Assign teachers to '.$program.' before creating program classes.');
                }
            }

            // Legacy classes are the existing Khmer curriculum. Keep their IDs,
            // students, subjects and schedules; they may span multiple sessions.
            foreach (SchoolClass::whereNull('program')->orderBy('id')->get() as $index => $class) {
                $pool = $teachers['Khmer'];
                $class->update(['program' => 'Khmer', 'teacher_id' => $pool[$index % $pool->count()]->id]);
            }

            foreach (['English' => 'EN', 'Chinese' => 'ZH'] as $program => $prefix) {
                $pool = $teachers[$program];
                $index = 0;
                $sessions = $program === 'Chinese' ? ['Evening'] : array_keys(SchoolClass::SESSION_HOURS);
                foreach (range(1, 12) as $grade) {
                    foreach (['A', 'B'] as $section) {
                        foreach ($sessions as $session) {
                            SchoolClass::firstOrCreate([
                                'name' => 'Grade '.$grade, 'section' => $section,
                                'program' => $program, 'session' => $session,
                            ], [
                                'teacher_id' => $pool[$index++ % $pool->count()]->id,
                                'room' => $prefix.'-'.$grade.$section, 'capacity' => 40,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
