<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedules') && Schema::hasTable('vehicles')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
            });
        }
    }
};
