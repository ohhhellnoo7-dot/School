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
