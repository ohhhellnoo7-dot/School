<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            // Old prices retain their original three-month meaning.
            $table->unsignedSmallInteger('package_months')->default(3);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Package duration is part of fee history.');
    }
};
