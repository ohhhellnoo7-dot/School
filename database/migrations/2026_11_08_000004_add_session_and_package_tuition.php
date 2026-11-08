<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->decimal('quarterly_tuition', 12, 2)->nullable();
            $table->json('session_prices')->nullable();
        });
        Schema::table('fees', function (Blueprint $table) {
            $table->string('session', 20)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Tuition pricing history requires a reviewed archival migration.');
    }
};
