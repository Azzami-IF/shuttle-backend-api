<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasTable('seats')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->foreign('seat_id')->references('id')->on('seats')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['seat_id']);
            });
        }
    }
};
