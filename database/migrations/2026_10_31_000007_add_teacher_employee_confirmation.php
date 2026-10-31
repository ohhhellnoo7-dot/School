<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users',function(Blueprint $table) {
            $table->timestamp('employee_confirmed_at')->nullable();
            $table->string('employee_confirmation_source')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users',fn(Blueprint $table)=>$table->dropColumn(['employee_confirmed_at','employee_confirmation_source']));
    }
};
