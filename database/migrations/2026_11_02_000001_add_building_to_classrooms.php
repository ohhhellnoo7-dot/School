<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('building', 10)->nullable()->index();
        });

        foreach (config('classrooms.rooms', []) as $room) {
            DB::table('classrooms')->where('room_number', $room['room_number'])
                ->update(['building' => $room['building']]);
        }
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex(['building']);
            $table->dropColumn('building');
        });
    }
};
