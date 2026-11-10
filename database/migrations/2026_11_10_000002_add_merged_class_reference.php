<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('school_classes', fn (Blueprint $table) => $table->foreignId('merged_into_class_id')->nullable()->constrained('school_classes')->restrictOnDelete());
    }
    public function down(): void {
        Schema::table('school_classes', fn (Blueprint $table) => $table->dropConstrainedForeignId('merged_into_class_id'));
    }
};
