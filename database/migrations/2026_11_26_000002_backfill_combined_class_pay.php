<?php

use App\Services\CombinedClassPay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('temporary_class_studies as study')
            ->join('schedules as schedule', 'schedule.id', '=', 'study.target_schedule_id')
            ->join('users as teacher', 'teacher.id', '=', 'schedule.teacher_id')
            ->where('teacher.role', 'teacher')
            ->whereNull('study.covering_teacher_id')
            ->whereNull('study.salary_payment_id')
            ->where('study.coverage_cents', 0)
            ->select(['study.id', 'study.starts_at', 'study.ends_at', 'study.teacher_name', 'teacher.name', 'teacher.id as teacher_id'])
            ->chunkById(200, function ($studies) {
                foreach ($studies as $study) {
                    // Confirm the current schedule still names the teacher recorded when the classes joined.
                    if ($study->teacher_name !== $study->name) {
                        continue;
                    }

                    $seconds = static function (string $time): int {
                        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));

                        return $hours * 3600 + $minutes * 60 + $seconds;
                    };
                    $duration = $seconds($study->ends_at) - $seconds($study->starts_at);
                    if ($duration <= 0) {
                        continue;
                    }

                    DB::table('temporary_class_studies')->where('id', $study->id)
                        ->whereNull('covering_teacher_id')->whereNull('salary_payment_id')
                        ->update([
                            'covering_teacher_id' => $study->teacher_id,
                            'coverage_cents' => (int) round($duration * CombinedClassPay::HOURLY_CENTS / 3600),
                        ]);
                }
            }, 'study.id', 'id');
    }

    public function down(): void
    {
        // Keep the recovered earnings and any payment linked to them.
    }
};
