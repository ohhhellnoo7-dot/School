<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('grade');
            $table->string('section', 5)->nullable();
            $table->string('program', 20)->index();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('capacity')->default(40);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['grade', 'section']);
        });

        // A normal composite UNIQUE treats NULL sections as distinct. Both
        // PostgreSQL and SQLite support this expression index, which gives
        // English and Chinese exactly one sectionless identity per grade.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX class_groups_program_grade_section_unique
            ON class_groups (program, grade, UPPER(COALESCE(section, '')))
        SQL);

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->string('session', 20);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->unique(['class_group_id', 'session'], 'class_sessions_group_session_unique');
            $table->unique(['id', 'class_group_id'], 'class_sessions_id_group_unique');
            $table->index(['session', 'starts_at', 'ends_at']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 20)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('capacity')->default(40);
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('classroom_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('day_of_week', 12);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('starts_minute');
            $table->unsignedSmallInteger('ends_minute');
            $table->timestamps();

            $table->unique(
                ['class_group_id', 'class_session_id', 'day_of_week'],
                'classroom_bookings_group_session_day_unique'
            );
            $table->foreign(
                ['class_session_id', 'class_group_id'],
                'classroom_bookings_session_group_foreign'
            )->references(['id', 'class_group_id'])
                ->on('class_sessions')
                ->restrictOnDelete();
            $table->index(
                ['classroom_id', 'day_of_week', 'starts_minute', 'ends_minute'],
                'classroom_bookings_room_time_index'
            );
            $table->index(
                ['teacher_id', 'day_of_week', 'starts_minute', 'ends_minute'],
                'classroom_bookings_teacher_time_index'
            );
            $table->index(
                ['class_group_id', 'day_of_week', 'starts_minute', 'ends_minute'],
                'classroom_bookings_group_time_index'
            );
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('class_group_id')
                ->nullable()
                ->after('id')
                ->constrained('class_groups')
                ->nullOnDelete();
            $table->index('class_group_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->addPostgresConstraints();
        }
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_group_id');
        });

        Schema::dropIfExists('classroom_bookings');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('class_groups');
    }

    private function addPostgresConstraints(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            ALTER TABLE class_groups
            ADD CONSTRAINT class_groups_program_check
            CHECK (program IN ('Khmer', 'English', 'Chinese')),
            ADD CONSTRAINT class_groups_status_check
            CHECK (status IN ('active', 'inactive')),
            ADD CONSTRAINT class_groups_grade_check
            CHECK (grade BETWEEN 1 AND 12),
            ADD CONSTRAINT class_groups_identity_check
            CHECK (
                (program = 'Khmer' AND grade BETWEEN 7 AND 12 AND section IN ('A', 'B', 'C', 'D', 'E'))
                OR
                (program IN ('English', 'Chinese') AND section IS NULL)
            ),
            ADD CONSTRAINT class_groups_capacity_check
            CHECK (capacity > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE class_sessions
            ADD CONSTRAINT class_sessions_name_check
            CHECK (session IN ('Morning', 'Afternoon', 'Evening')),
            ADD CONSTRAINT class_sessions_time_check
            CHECK (starts_at < ends_at)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE classrooms
            ADD CONSTRAINT classrooms_status_check
            CHECK (status IN ('active', 'out_of_service')),
            ADD CONSTRAINT classrooms_capacity_check
            CHECK (capacity > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE classroom_bookings
            ADD CONSTRAINT classroom_bookings_day_check
            CHECK (day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday')),
            ADD CONSTRAINT classroom_bookings_time_check
            CHECK (
                starts_at < ends_at
                AND starts_minute < ends_minute
                AND starts_minute = EXTRACT(HOUR FROM starts_at)::integer * 60
                    + EXTRACT(MINUTE FROM starts_at)::integer
                AND ends_minute = EXTRACT(HOUR FROM ends_at)::integer * 60
                    + EXTRACT(MINUTE FROM ends_at)::integer
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE classroom_bookings
            ADD CONSTRAINT classroom_bookings_room_time_excl
            EXCLUDE USING gist (
                classroom_id WITH =,
                day_of_week WITH =,
                int4range(starts_minute, ends_minute, '[)') WITH &&
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE classroom_bookings
            ADD CONSTRAINT classroom_bookings_class_time_excl
            EXCLUDE USING gist (
                class_group_id WITH =,
                day_of_week WITH =,
                int4range(starts_minute, ends_minute, '[)') WITH &&
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE classroom_bookings
            ADD CONSTRAINT classroom_bookings_teacher_time_excl
            EXCLUDE USING gist (
                teacher_id WITH =,
                day_of_week WITH =,
                int4range(starts_minute, ends_minute, '[)') WITH &&
            )
            WHERE (teacher_id IS NOT NULL)
        SQL);
    }
};
