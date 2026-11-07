<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_locks', function (Blueprint $t) {
            $t->unsignedSmallInteger('id')->primary();
        });
        DB::table('finance_locks')->insert(['id' => 1]);
        Schema::create('fee_structures', function (Blueprint $t) {
            $t->id();
            $t->string('academic_year', 9);
            $t->string('program', 20);
            $t->unsignedSmallInteger('grade');
            $t->string('currency', 3);
            $t->decimal('monthly_tuition', 12, 2);
            $t->unsignedSmallInteger('billable_months');
            $t->unsignedSmallInteger('semester_months')->default(6);
            $t->date('starts_on');
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->unsignedSmallInteger('due_day')->default(10);
            $t->json('charges');
            $t->decimal('discount', 12, 2)->default(0);
            $t->string('discount_reason')->nullable();
            $t->string('status')->default('draft');
            $t->foreignId('supersedes_id')->nullable()->constrained('fee_structures')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::table('fees', function (Blueprint $t) {
            $t->dropForeign(['student_id']);
            $t->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $t->string('invoice_number')->nullable()->unique();
            $t->foreignId('fee_structure_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('academic_year', 9)->nullable();
            $t->string('program', 20)->nullable();
            $t->unsignedSmallInteger('grade')->nullable();
            $t->string('currency', 3)->default('USD');
            $t->string('enrollment_key')->nullable();
            $t->string('payment_plan')->nullable();
            $t->decimal('opening_paid', 12, 2)->default(0);
            $t->boolean('legacy_reviewed')->default(false);
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // Keep legacy paid totals intact; their dates/methods are unknown, not invented receipts.
        DB::table('fees')->update(['opening_paid' => DB::raw('paid')]);
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fee_id')->constrained('fees')->restrictOnDelete();
            $t->string('description');
            $t->decimal('amount', 12, 2);
        });
        Schema::create('invoice_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fee_id')->constrained('fees')->restrictOnDelete();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->string('academic_year', 9);
            $t->string('enrollment_key');
            $t->unsignedSmallInteger('month_index');
            $t->unique(['student_id', 'academic_year', 'enrollment_key', 'month_index'], 'unique_enrollment_billing_month');
        });
        Schema::create('fee_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fee_id')->constrained('fees')->restrictOnDelete();
            $t->string('receipt_number')->unique();
            $t->uuid('request_key')->unique();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3);
            $t->date('paid_on');
            $t->string('method', 30);
            $t->string('reference')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('financial_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fee_id')->constrained('fees')->restrictOnDelete();
            $t->foreignId('fee_payment_id')->nullable()->constrained()->restrictOnDelete();
            $t->uuid('request_key')->unique();
            $t->string('kind', 20);
            $t->decimal('amount', 12, 2);
            $t->string('reason', 500);
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('finance_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Financial history must be restored from a reviewed backup, never silently dropped.
        throw new RuntimeException('Finance ledger rollback requires an explicit archival migration.');
    }
};
