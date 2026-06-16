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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            // vehicle FK added in a later migration to avoid ordering issues
            $table->unsignedBigInteger('vehicle_id');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('origin');
            $table->string('destination');
            $table->dateTime('departure_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
