<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION check_teacher_break_plan() RETURNS trigger LANGUAGE plpgsql AS $$
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
            SQL);
    }

    public function down(): void
    {
        // Keep the corrected integrity function when rolling back this repair.
    }
};
