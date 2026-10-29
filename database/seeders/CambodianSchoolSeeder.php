<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CambodianSchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Fictional school data is disabled in production.');
        }
        DB::transaction(function () {
            $this->call([
                TeacherSeeder::class, ClassSeeder::class, SubjectSeeder::class, ParentSeeder::class,
                StudentSeeder::class, AttendanceSeeder::class, ExaminationSeeder::class,
                PaymentSeeder::class, TimetableSeeder::class, AnnouncementSeeder::class,
            ]);
        });
        $this->command?->info('Fictional Cambodian school data added. Existing records were preserved.');
    }
}
