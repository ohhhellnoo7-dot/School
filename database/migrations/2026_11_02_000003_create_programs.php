<?php

use App\Support\ProgramSessions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->json('sessions');
            $table->timestamps();
        });
        foreach (ProgramSessions::BY_PROGRAM as $name => $sessions) {
            DB::table('programs')->insert(['name' => $name, 'sessions' => json_encode($sessions), 'created_at' => now(), 'updated_at' => now()]);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE class_groups DROP CONSTRAINT class_groups_program_check');
            DB::statement('ALTER TABLE class_groups DROP CONSTRAINT class_groups_identity_check');
            DB::statement("ALTER TABLE class_groups ADD CONSTRAINT class_groups_identity_check CHECK ((program = 'Khmer' AND grade BETWEEN 7 AND 12 AND section IN ('A','B','C','D','E')) OR (program <> 'Khmer' AND section IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
