<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('payments') && Schema::hasTable('bookings')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // ignore if foreign key cannot be created or already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                try { $table->dropForeign(['booking_id']); } catch (\Exception $e) {}
            });
        }
    }
};
