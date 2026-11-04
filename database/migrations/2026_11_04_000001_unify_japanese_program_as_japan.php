<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $legacy = DB::table('programs')->where('name', 'Japanese')->first();
            $canonical = DB::table('programs')->where('name', 'Japan')->first();
            if ($legacy && $canonical) {
                $sessions = array_values(array_unique(array_merge(json_decode($canonical->sessions, true), json_decode($legacy->sessions, true))));
                DB::table('programs')->where('id', $canonical->id)->update(['sessions' => json_encode($sessions)]);
                DB::table('programs')->where('id', $legacy->id)->delete();
            } elseif ($legacy) {
                DB::table('programs')->where('id', $legacy->id)->update(['name' => 'Japan']);
            }
            foreach (['users', 'class_groups', 'school_classes'] as $table) {
                DB::table($table)->where('program', 'Japanese')->update(['program' => 'Japan']);
            }
        });
    }

    public function down(): void
    {
        // The original names cannot be recovered after merging the two labels.
    }
};
