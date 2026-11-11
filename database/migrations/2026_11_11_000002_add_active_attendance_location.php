<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_locations', function (Blueprint $table) {
            // NULL means history; the unique value 1 permits only one active zone.
            $table->boolean('is_active')->nullable()->unique();
        });
        $latest = DB::table('school_locations')->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['administrator current location (test)'])
            ->orderByDesc('id')->value('id');
        if ($latest) DB::table('school_locations')->where('id', $latest)->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('school_locations', function (Blueprint $table) {
            $table->dropUnique(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
