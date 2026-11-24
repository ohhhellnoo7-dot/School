<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_session_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('session', 20);
            $table->date('effective_on');
            $table->timestamp('applied_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'session', 'effective_on']);
            $table->index(['applied_at', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_session_stops');
    }
};
