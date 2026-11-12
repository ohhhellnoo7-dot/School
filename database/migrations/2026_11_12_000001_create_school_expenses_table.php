<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('school_expenses', function (Blueprint $table) {
  $table->id(); $table->uuid('request_key')->unique(); $table->decimal('amount', 12, 2);
  $table->string('currency', 3); $table->date('spent_on')->index(); $table->text('note')->nullable();
  $table->foreignId('recorded_by')->constrained('users'); $table->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('school_expenses'); }
};
