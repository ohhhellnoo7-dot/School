<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_management_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->unsignedInteger('target')->default(30);
            $t->unsignedInteger('weekly_minutes')->default(1200);
        });
        DB::table('teacher_management_settings')->insert(['id' => 1, 'target' => 30, 'weekly_minutes' => 1200]);
        Schema::create('teacher_profiles', function (Blueprint $t) {
            $t->foreignId('teacher_id')->primary()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('weekly_minutes')->nullable();
        });
        Schema::create('teacher_qualifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->string('subject_name');
            $t->unsignedSmallInteger('slot');
            $t->unique(['teacher_id', 'subject_name']);
            $t->unique(['teacher_id', 'slot']);
        });
        Schema::create('teacher_working_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->string('weekday', 12);
            $t->time('start_time');
            $t->time('end_time');
            $t->boolean('unavailable')->default(false);
            $t->index(['teacher_id', 'weekday']);
        });
        Schema::create('teacher_break_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_id')->unique()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('teacher_breaks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('teacher_break_plan_id')->constrained()->cascadeOnDelete();
            $t->string('weekday', 12);
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedInteger('duration_minutes')->default(60);
            $t->timestamps();
            $t->unique(['teacher_break_plan_id', 'weekday']);
        });
        Schema::table('subjects', function (Blueprint $t) { $t->unsignedInteger('required_weekly_minutes')->nullable(); });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE teacher_qualifications ADD CONSTRAINT teacher_qualification_slot CHECK (slot IN (1,2))');
            DB::statement("ALTER TABLE teacher_breaks ADD CONSTRAINT teacher_break_hour CHECK (duration_minutes = 60 AND end_time - start_time = interval '1 hour' AND weekday IN ('Monday','Tuesday','Wednesday','Thursday','Friday'))");
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION check_teacher_break_plan() RETURNS trigger LANGUAGE plpgsql AS $$
                DECLARE plan_id bigint;
                BEGIN
                    IF TG_TABLE_NAME = 'teacher_break_plans' THEN
                        plan_id := COALESCE(NEW.id, OLD.id);
                    ELSIF TG_OP = 'DELETE' THEN
                        plan_id := OLD.teacher_break_plan_id;
                    ELSE
                        plan_id := NEW.teacher_break_plan_id;
                    END IF;
                    IF EXISTS (SELECT 1 FROM teacher_break_plans WHERE id = plan_id)
                       AND (SELECT COUNT(*) FROM teacher_breaks WHERE teacher_break_plan_id = plan_id) <> 3 THEN
                        RAISE EXCEPTION 'A completed weekly teacher break plan requires exactly three breaks' USING ERRCODE = '23514';
                    END IF;
                    IF TG_TABLE_NAME = 'teacher_breaks' THEN
                      IF TG_OP = 'UPDATE' THEN
                       IF OLD.teacher_break_plan_id <> NEW.teacher_break_plan_id THEN
                        IF EXISTS (SELECT 1 FROM teacher_break_plans WHERE id = OLD.teacher_break_plan_id)
                           AND (SELECT COUNT(*) FROM teacher_breaks WHERE teacher_break_plan_id = OLD.teacher_break_plan_id) <> 3 THEN
                            RAISE EXCEPTION 'The original break plan must retain three breaks' USING ERRCODE = '23514';
                        END IF;
                    END IF;
                      END IF;
                    END IF;
                    RETURN NULL;
                END;
                $$;
                CREATE CONSTRAINT TRIGGER teacher_break_count AFTER INSERT OR UPDATE OR DELETE ON teacher_breaks DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_teacher_break_plan();
                CREATE CONSTRAINT TRIGGER teacher_plan_count AFTER INSERT OR UPDATE ON teacher_break_plans DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_teacher_break_plan();
                SQL);
        } else {
            DB::unprepared("CREATE TRIGGER teacher_qualification_slot_insert BEFORE INSERT ON teacher_qualifications WHEN NEW.slot NOT IN (1,2) BEGIN SELECT RAISE(ABORT, 'Maximum two subjects'); END");
            DB::unprepared("CREATE TRIGGER teacher_qualification_slot_update BEFORE UPDATE ON teacher_qualifications WHEN NEW.slot NOT IN (1,2) BEGIN SELECT RAISE(ABORT, 'Maximum two subjects'); END");
        }
    }

    public function down(): void
    {
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('required_weekly_minutes'));
        Schema::dropIfExists('teacher_breaks');
        Schema::dropIfExists('teacher_break_plans');
        if (DB::getDriverName() === 'pgsql') DB::statement('DROP FUNCTION IF EXISTS check_teacher_break_plan()');
        Schema::dropIfExists('teacher_working_periods');
        Schema::dropIfExists('teacher_qualifications');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('teacher_management_settings');
    }
};
