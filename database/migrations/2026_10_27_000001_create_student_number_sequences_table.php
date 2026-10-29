<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_number_sequences', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        $lastNumber = 0;
        foreach (DB::table('students')->select('student_number')->cursor() as $student) {
            if (preg_match('/^STU(\d+)$/', $student->student_number, $matches)) {
                $lastNumber = max($lastNumber, (int) $matches[1]);
            }
        }
        DB::table('student_number_sequences')->insert(['id' => 1, 'last_number' => $lastNumber]);
    }

    public function down(): void
    {
        Schema::dropIfExists('student_number_sequences');
    }
};
