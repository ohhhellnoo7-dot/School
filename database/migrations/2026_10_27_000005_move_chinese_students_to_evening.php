<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $classes = DB::table('school_classes')->where('program', 'Chinese')
                ->whereIn('session', ['Morning', 'Afternoon'])->orderBy('id')->get();

            foreach ($classes as $class) {
                if (! DB::table('students')->where('school_class_id', $class->id)->exists()) {
                    continue;
                }

                $evening = DB::table('school_classes')->where('program', 'Chinese')
                    ->where('name', $class->name)->where('section', $class->section)
                    ->where('session', 'Evening')->first();

                $eveningId = $evening?->id;
                if (! $eveningId) {
                    $attributes = (array) $class;
                    unset($attributes['id']);
                    $attributes['session'] = 'Evening';
                    $attributes['created_at'] = $attributes['updated_at'] = now();
                    $eveningId = DB::table('school_classes')->insertGetId($attributes);
                }

                // Keep additional enrollment records consistent if this repair is rerun.
                if (\Illuminate\Support\Facades\Schema::hasTable('student_class_enrollments')) {
                    $studentIds = DB::table('students')->where('school_class_id', $class->id)->pluck('id');
                    DB::table('student_class_enrollments')->whereIn('student_id', $studentIds)->where('school_class_id', $class->id)->delete();
                    $groupId = DB::table('school_classes')->where('id', $eveningId)->value('class_group_id');
                    foreach ($studentIds as $studentId) {
                        DB::table('student_class_enrollments')->insertOrIgnore(['student_id' => $studentId, 'school_class_id' => $eveningId, 'class_group_id' => $groupId]);
                    }
                }
                DB::table('students')->where('school_class_id', $class->id)
                    ->update(['school_class_id' => $eveningId, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Student transfers remain in place when rolling back application code.
    }
};
