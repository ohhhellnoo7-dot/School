<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE teacher_qualifications DROP CONSTRAINT teacher_qualification_slot');
            DB::statement('ALTER TABLE teacher_qualifications ADD CONSTRAINT teacher_qualification_slot CHECK (slot > 0)');
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION check_khmer_qualification_slot() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.slot > 2 AND EXISTS (SELECT 1 FROM users WHERE id = NEW.teacher_id AND COALESCE(NULLIF(program, ''), 'Khmer') = 'Khmer') THEN
                        RAISE EXCEPTION 'Khmer teachers may have at most two subjects' USING ERRCODE = '23514';
                    END IF;
                    RETURN NEW;
                END;
                $$;
                CREATE TRIGGER khmer_qualification_slot BEFORE INSERT OR UPDATE ON teacher_qualifications FOR EACH ROW EXECUTE FUNCTION check_khmer_qualification_slot();
                SQL);
        } else {
            DB::unprepared('DROP TRIGGER teacher_qualification_slot_insert; DROP TRIGGER teacher_qualification_slot_update;');
            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $event) DB::unprepared("CREATE TRIGGER teacher_qualification_slot_{$suffix} BEFORE {$event} ON teacher_qualifications WHEN NEW.slot < 1 OR (NEW.slot > 2 AND EXISTS (SELECT 1 FROM users WHERE id = NEW.teacher_id AND COALESCE(NULLIF(program, ''), 'Khmer') = 'Khmer')) BEGIN SELECT RAISE(ABORT, 'Khmer teachers may have at most two subjects'); END");
        }
    }

    public function down(): void
    {
        if (DB::table('teacher_qualifications')->where('slot','>',2)->exists()) throw new RuntimeException('Reassign extra English/Chinese qualifications before restoring the universal two-subject limit.');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER khmer_qualification_slot ON teacher_qualifications; DROP FUNCTION check_khmer_qualification_slot();');
            DB::statement('ALTER TABLE teacher_qualifications DROP CONSTRAINT teacher_qualification_slot');
            DB::statement('ALTER TABLE teacher_qualifications ADD CONSTRAINT teacher_qualification_slot CHECK (slot IN (1,2))');
        } else {
            DB::unprepared('DROP TRIGGER teacher_qualification_slot_insert; DROP TRIGGER teacher_qualification_slot_update;');
            foreach (['insert'=>'INSERT','update'=>'UPDATE'] as $suffix=>$event) DB::unprepared("CREATE TRIGGER teacher_qualification_slot_{$suffix} BEFORE {$event} ON teacher_qualifications WHEN NEW.slot NOT IN (1,2) BEGIN SELECT RAISE(ABORT, 'Maximum two subjects'); END");
        }
    }
};
