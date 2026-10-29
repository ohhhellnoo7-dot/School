<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->index();
            $table->string('phone')->nullable();
        });
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('section');
            $table->string('room')->nullable();
            $table->unsignedInteger('capacity')->default(40);
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'section']);
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number')->unique();
            $table->string('name');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_name');
            $table->string('guardian_phone');
            $table->date('enrollment_date');
            $table->string('photo')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_marks')->default(100);
            $table->timestamps();
        });
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks', 7, 2);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'examination_id']);
        });
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->date('due_date');
            $table->timestamps();
        });
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room');
            $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('all');
            $table->date('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcements', 'schedules', 'assignments', 'fees', 'grades', 'examinations', 'attendances', 'subjects', 'students', 'school_classes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'phone']));
    }
};
