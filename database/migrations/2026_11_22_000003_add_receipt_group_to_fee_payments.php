<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_payments', fn (Blueprint $table) => $table->uuid('receipt_group')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex(['receipt_group']);
            $table->dropColumn('receipt_group');
        });
    }
};
