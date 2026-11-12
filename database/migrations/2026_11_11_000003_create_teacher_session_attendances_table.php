<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teacher_session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('session');
            $table->string('status');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'date', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_session_attendances');
    }
};
