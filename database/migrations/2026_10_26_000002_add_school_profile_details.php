<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_number')->nullable()->unique();
            $table->string('gender')->nullable();
            $table->text('address')->nullable();
            $table->string('qualification')->nullable();
            $table->date('hire_date')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('status')->default('active');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->string('guardian_relationship')->nullable();
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('guardian_relationship'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['profile_number']);
            $table->dropColumn(['profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status']);
        });
    }
};
