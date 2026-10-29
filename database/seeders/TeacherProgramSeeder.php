<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeacherProgramSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $teachers = User::where('role', 'teacher')->orderBy('id')->lockForUpdate()->get();
            if ($teachers->count() !== 30) {
                throw new \RuntimeException('Expected exactly 30 teachers before assigning 10 per program.');
            }

            foreach ($teachers as $index => $teacher) {
                $program = ['Khmer', 'English', 'Chinese'][intdiv($index, 10)];
                if ($teacher->program !== $program) {
                    $teacher->update(['program' => $program]);
                }
            }
        });
    }
}
