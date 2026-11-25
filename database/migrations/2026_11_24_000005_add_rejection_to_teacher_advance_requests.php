<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_advance_requests', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_advance_requests', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
