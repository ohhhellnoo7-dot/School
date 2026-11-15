<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->string('checkout_method')->nullable();
            $table->timestamp('checkout_submitted_at')->nullable();
            $table->string('checkout_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fees', fn (Blueprint $table) => $table->dropColumn(['checkout_method', 'checkout_submitted_at', 'checkout_reference']));
    }
};
