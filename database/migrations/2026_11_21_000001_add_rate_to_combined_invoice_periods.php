<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_periods', function (Blueprint $table) {
            $table->foreignId('fee_structure_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_structure_id');
        });
    }
};
