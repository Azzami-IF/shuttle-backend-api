<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->string('origin');
            $table->string('destination');
            $table->time('departure_time');        // Jam keberangkatan (misal: 08:00:00)
            $table->decimal('price', 10, 2)->default(85000);
            // Hari aktif: JSON array, misal [0,1,2,3,4,5,6] = semua hari (0=Minggu, 6=Sabtu)
            $table->json('active_days'); 
            $table->integer('generate_days_ahead')->default(30); // Berapa hari ke depan di-generate
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_templates');
    }
};
