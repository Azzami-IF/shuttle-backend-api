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
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('pickup_name')->nullable();
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->string('drop_off_name')->nullable();
            $table->decimal('drop_off_lat', 10, 8)->nullable();
            $table->decimal('drop_off_lng', 11, 8)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_name',
                'pickup_lat',
                'pickup_lng',
                'drop_off_name',
                'drop_off_lat',
                'drop_off_lng'
            ]);
        });
    }
};
