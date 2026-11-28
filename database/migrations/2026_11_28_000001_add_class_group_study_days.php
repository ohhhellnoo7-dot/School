<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_groups', fn (Blueprint $table) => $table->string('class_days', 10)->nullable());
        DB::statement('DROP INDEX class_groups_program_grade_section_unique');
        DB::statement("CREATE UNIQUE INDEX class_groups_program_grade_section_unique ON class_groups (program, grade, UPPER(COALESCE(section, '')), COALESCE(class_days, 'weekday'))");
    }

    public function down(): void
    {
        DB::statement("CREATE UNIQUE INDEX class_groups_legacy_identity ON class_groups (program, grade, UPPER(COALESCE(section, '')))");
        DB::statement('DROP INDEX class_groups_program_grade_section_unique');
        DB::statement("CREATE UNIQUE INDEX class_groups_program_grade_section_unique ON class_groups (program, grade, UPPER(COALESCE(section, '')))");
        DB::statement('DROP INDEX class_groups_legacy_identity');
        Schema::table('class_groups', fn (Blueprint $table) => $table->dropColumn('class_days'));
    }
};
