<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_payment_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->unique()->constrained('users')->restrictOnDelete();
            $t->date('next_payment_on')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_payment_reminders');
    }
};
