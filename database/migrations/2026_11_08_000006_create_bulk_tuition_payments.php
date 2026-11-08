<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_payment_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->date('starts_on')->unique();
            $t->date('anchor_on');
            $t->unsignedInteger('months_before')->default(0);
            $t->unsignedSmallInteger('months');
            $t->date('next_on');
            $t->json('totals');
            $t->unsignedInteger('parents_count');
            $t->unsignedInteger('students_count');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->string('method', 30);
            $t->string('reference')->nullable();
            $t->timestamps();
        });
        Schema::create('bulk_payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bulk_payment_run_id')->constrained()->restrictOnDelete();
            $t->foreignId('fee_id')->constrained('fees')->restrictOnDelete();
            $t->foreignId('fee_payment_id')->nullable()->constrained()->restrictOnDelete();
            $t->unique(['bulk_payment_run_id', 'fee_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Bulk payment history requires an archival migration.');
    }
};
