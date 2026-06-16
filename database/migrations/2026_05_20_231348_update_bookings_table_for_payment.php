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
        Schema::table('bookings', function (Blueprint $table) {
            // Change status to string for more flexibility and add payment_code
            $table->string('status')->default('pending_payment')->change();
            $table->string('payment_code')->nullable()->after('seat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status')->default('booked')->change(); // Fallback
            $table->dropColumn('payment_code');
        });
    }
};
