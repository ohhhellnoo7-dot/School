<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->string('code', 100)->primary();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
        Schema::table('classrooms', fn (Blueprint $table) => $table->string('building', 100)->nullable()->change());
        foreach (collect(['A', 'B'])->merge(DB::table('classrooms')->whereNotNull('building')->distinct()->pluck('building'))->unique() as $code) {
            DB::table('buildings')->insert(['code' => $code, 'name' => 'Building '.$code, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
