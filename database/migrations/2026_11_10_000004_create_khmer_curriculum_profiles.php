<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('curriculum_profiles', function (Blueprint $t) {
            $t->id(); $t->string('program', 20)->default('Khmer'); $t->unsignedSmallInteger('grade'); $t->string('stream', 30)->default('');
            $t->string('status', 20)->default('provisional'); $t->text('notes')->nullable(); $t->timestamps(); $t->unique(['program','grade','stream']);
        });
        Schema::create('curriculum_requirements', function (Blueprint $t) {
            $t->id(); $t->foreignId('curriculum_profile_id')->constrained()->cascadeOnDelete(); $t->string('name', 100);
            $t->unsignedInteger('weekly_minutes'); $t->unsignedInteger('lesson_minutes'); $t->unsignedInteger('position'); $t->timestamps();
            $t->unique(['curriculum_profile_id','name']);
        });
        Schema::table('subjects', fn (Blueprint $t) => $t->foreignId('curriculum_requirement_id')->nullable()->constrained('curriculum_requirements')->nullOnDelete());
    }
    public function down(): void {
        Schema::table('subjects', fn (Blueprint $t) => $t->dropConstrainedForeignId('curriculum_requirement_id'));
        Schema::dropIfExists('curriculum_requirements'); Schema::dropIfExists('curriculum_profiles');
    }
};
