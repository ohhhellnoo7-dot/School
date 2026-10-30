<?php

namespace App\Services;

use App\Models\{Schedule, SchoolClass};
use Illuminate\Support\Facades\DB;

/** Stages all selected cohorts in one transaction, then always rolls preview back. */
class UnifiedTimetableGenerator
{
    public function __construct(private TeacherManagementService $rules, private RotationTimetableGenerator $rotation) {}

    public function preview(array $classIds, ?string $academicYear = null): array
    {
        return $this->rules->locked(function () use ($classIds, $academicYear) {
            $classes = SchoolClass::whereIn('id', $classIds)->orderBy('id')->get();
            if ($classes->isEmpty() || $classes->count() !== count(array_unique($classIds)) || $classes->contains(fn ($c) => $c->program !== 'Khmer')) $this->rules->fail('Select existing Khmer cohorts only.');
            if (Schedule::whereNotIn('school_class_id', $classes->modelKeys())->whereNull('classroom_id')->exists()) $this->rules->fail('Existing lessons outside this replacement lack physical classroom IDs. Review those room assignments before availability can be verified across programs.');
            $fingerprint = $this->rotation->fingerprint();
            $replace = Schedule::whereIn('school_class_id', $classIds)->exists();
            $plans = [];
            $relaxation = Schedule::whereIn('school_class_id', $classIds)->where('entry_type', 'relaxation')->get()->groupBy('school_class_id');
            DB::beginTransaction();
            try {
                // Retire selected old lessons only inside this rolled-back simulation.
                Schedule::whereIn('school_class_id', $classIds)->update(['cancelled_at' => now()]);
                foreach ($classes as $class) {
                    $plan = $this->rotation->preview($class, false, $relaxation->get($class->id, collect()));
                    foreach ($plan['rows'] as &$row) $row['academic_year'] = $academicYear ?? now('Asia/Phnom_Penh')->year.'-'.(now('Asia/Phnom_Penh')->year + 1);
                    unset($row);
                    $this->rotation->confirm($plan);
                    $plans[] = $plan;
                }
                return ['plans' => $plans, 'fingerprint' => $fingerprint, 'replace' => $replace, 'academic_year' => $academicYear, 'class_ids' => $classes->modelKeys(), 'validation' => app(KhmerClassTimetableReport::class)->report()];
            } finally {
                DB::rollBack();
            }
        });
    }

    public function confirm(array $batch): string
    {
        return $this->rules->locked(function () use ($batch) {
            if (! hash_equals($batch['fingerprint'], $this->rotation->fingerprint())) $this->rules->fail('Scheduling data changed. Generate another preview.');
            $backup = app(TimetableBackup::class)->create();
            $ids = array_column($batch['plans'], 'class_id');
            if (SchoolClass::whereIn('id', $ids)->where('program', 'Khmer')->count() !== count($ids)) $this->rules->fail('Replacement must contain Khmer cohorts only.');
            Schedule::whereIn('school_class_id', $ids)->update(['cancelled_at' => now()]);
            foreach ($batch['plans'] as $plan) {
                // Original batch fingerprint was verified under the same lock. Each
                // following plan now sees the already confirmed preceding cohorts.
                $plan['fingerprint'] = $this->rotation->fingerprint();
                $this->rotation->confirm($plan);
            }
            if (! empty($batch['complete_school'])) {
                $expected = SchoolClass::where('program', 'Khmer')->pluck('id')->sort()->values()->all();
                $actual = collect($ids)->sort()->values()->all();
                if ($expected !== $actual || app(KhmerClassTimetableReport::class)->report()['valid_identities'] !== 30) $this->rules->fail('All 30 Khmer identities and every existing cohort must pass validation before publication. No replacement was published.');
            }
            return $backup;
        });
    }
}
