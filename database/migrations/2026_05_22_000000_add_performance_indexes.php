<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add indexes for performance optimization
     */
    public function up(): void
    {
        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email');
                $table->index('role');
                $table->index('created_at');
            });
        }

        // Bookings table indexes
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index('user_id');
                $table->index('schedule_id');
                $table->index('seat_id');
                $table->index('status');
                $table->index('created_at');
                $table->index(['user_id', 'status']);
            });
        }

        // Schedules table indexes
        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->index('vehicle_id');
                $table->index('driver_id');
                $table->index('departure_time');
                $table->index('created_at');
                $table->index(['origin', 'destination']);
            });
        }

        // Trips table indexes
        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->index('schedule_id');
                $table->index('status');
                $table->index('created_at');
            });
        }

        // Locations table indexes
        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->index('trip_id');
                $table->index('created_at');
            });
        }

        // Seats table indexes
        if (Schema::hasTable('seats')) {
            Schema::table('seats', function (Blueprint $table) {
                $table->index('schedule_id');
                $table->index('status');
            });
        }

        // Vehicles table indexes
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop all indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['email']);
                $table->dropIndex(['role']);
                $table->dropIndex(['created_at']);
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['schedule_id']);
                $table->dropIndex(['seat_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['created_at']);
                $table->dropIndex(['user_id', 'status']);
            });
        }

        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex(['vehicle_id']);
                $table->dropIndex(['driver_id']);
                $table->dropIndex(['departure_time']);
                $table->dropIndex(['created_at']);
                $table->dropIndex(['origin', 'destination']);
            });
        }

        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->dropIndex(['schedule_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['created_at']);
            });
        }

        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropIndex(['trip_id']);
                $table->dropIndex(['created_at']);
            });
        }

        if (Schema::hasTable('seats')) {
            Schema::table('seats', function (Blueprint $table) {
                $table->dropIndex(['schedule_id']);
                $table->dropIndex(['status']);
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropIndex(['created_at']);
            });
        }
    }
};
