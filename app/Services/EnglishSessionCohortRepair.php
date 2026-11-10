<?php

namespace App\Services;

use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnglishSessionCohortRepair
{
    public function run(?string $backupPath = null): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($backupPath) {
            $aliases = SchoolClass::where('program', 'English')->whereNotNull('shared_timetable_class_id')->lockForUpdate()->get();
            $plan = [];
            foreach ($aliases as $alias) {
                $owner = SchoolClass::findOrFail($alias->shared_timetable_class_id);
                if ($owner->program !== 'English' || $owner->session !== $alias->session || $owner->class_group_id !== $alias->class_group_id || $owner->shared_timetable_class_id) throw new RuntimeException('Incompatible cohort '.$alias->id);
                if (DB::table('schedules')->where('school_class_id', $alias->id)->whereNull('cancelled_at')->exists()) throw new RuntimeException('Alias has its own active lessons: '.$alias->id);
                $marks = DB::table('class_attendances')->where('school_class_id', $alias->id)->get();
                foreach ($marks as $mark) {
                    if (DB::table('class_attendances')->where('school_class_id', $owner->id)->where('student_id', $mark->student_id)->where('date', $mark->date)->exists()) throw new RuntimeException('Review overlapping attendance before merging class '.$alias->id);
                }
                $plan[] = [
                    'owner' => $owner->toArray(), 'alias' => $alias->toArray(),
                    'enrollments' => DB::table('student_class_enrollments')->whereIn('school_class_id', [$owner->id, $alias->id])->get()->all(),
                    'primary_assignments' => DB::table('students')->where('school_class_id', $alias->id)->get(['id', 'school_class_id'])->all(),
                    'attendance' => $marks->all(),
                ];
            }
            if ($backupPath) {
                if (! is_dir(dirname($backupPath))) mkdir(dirname($backupPath), 0755, true);
                if (file_put_contents($backupPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Could not write backup.');
            }
            foreach ($plan as $item) {
                $owner = $item['owner']; $alias = $item['alias'];
                foreach ($item['enrollments'] as $enrollment) {
                    if ($enrollment->school_class_id !== $alias['id']) continue;
                    DB::table('student_class_enrollments')->insertOrIgnore(['student_id' => $enrollment->student_id, 'school_class_id' => $owner['id'], 'class_group_id' => $owner['class_group_id']]);
                }
                DB::table('student_class_enrollments')->where('school_class_id', $alias['id'])->delete();
                DB::table('students')->where('school_class_id', $alias['id'])->update(['school_class_id' => $owner['id'], 'updated_at' => now()]);
                DB::table('class_attendances')->where('school_class_id', $alias['id'])->update(['school_class_id' => $owner['id']]);
                DB::table('school_classes')->where('id', $alias['id'])->update(['merged_into_class_id' => $owner['id'], 'updated_at' => now()]);
                DB::table('school_classes')->where('id', $owner['id'])->update(['section' => '', 'updated_at' => now()]);
            }
            return array_map(fn ($item) => ['class_group_id' => $item['owner']['class_group_id'], 'session' => $item['owner']['session'], 'class_id' => $item['owner']['id'], 'merged_class_id' => $item['alias']['id']], $plan);
        });
    }
}
