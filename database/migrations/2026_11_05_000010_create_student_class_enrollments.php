<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['student_id', 'school_class_id']);
        });
        DB::table('students')->join('school_classes', 'school_classes.id', '=', 'students.school_class_id')
            ->select('students.id as student_id', 'students.school_class_id', 'school_classes.class_group_id')
            ->orderBy('students.id')->chunk(500, function ($rows) {
                DB::table('student_class_enrollments')->insert($rows->map(fn ($row) => (array) $row)->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_enrollments');
    }
};
