<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('locations') && Schema::hasTable('trips')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign(['trip_id']);
            });
        }
    }
};
