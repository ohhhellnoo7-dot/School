<?php

namespace Database\Seeders;

use App\Models\{Program, User};
use App\Services\TeacherManagementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JapanTeacherSeeder extends Seeder
{
    public const SUBJECTS = ['Japanese Grammar', 'Japanese Vocabulary', 'Japanese Reading & Writing', 'Japanese Listening', 'Japanese Speaking'];

    public function run(): void
    {
        app(TeacherManagementService::class)->locked(function () {
            Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Morning', 'Afternoon', 'Evening']]);
            for ($number = 1; $number <= 12; $number++) {
                $profile = 'JPN-REG-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $teacher = User::withoutGlobalScope('archived')->where('profile_number', $profile)->first();
                if (! $teacher) {
                    $teacher = new User;
                    $teacher->forceFill([
                        'name' => 'Japan Teacher '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                        'profile_number' => $profile, 'role' => 'teacher', 'program' => 'Japan',
                        'status' => 'active', 'gender' => 'other',
                        'email' => 'japan-staff-'.Str::uuid().'@example.test',
                        'password' => Str::random(48), 'teacher_record_source' => 'generated_unverified',
                    ])->save();
                }
                if ($teacher->role !== 'teacher' || $teacher->program !== 'Japan' || $teacher->archived_at) {
                    throw new \RuntimeException('Japan teacher profile number is already in use or archived: '.$profile);
                }
                DB::table('teacher_profiles')->insertOrIgnore(['teacher_id' => $teacher->id, 'weekly_minutes' => null]);
                foreach (self::SUBJECTS as $index => $subject) {
                    DB::table('teacher_qualifications')->updateOrInsert(
                        ['teacher_id' => $teacher->id, 'subject_name' => $subject],
                        ['slot' => $index + 1]
                    );
                }
            }
        });
    }
}
