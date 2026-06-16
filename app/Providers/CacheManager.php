<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheManager
{
    const CACHE_DURATION_SHORT = 300; // 5 minutes
    const CACHE_DURATION_MEDIUM = 3600; // 1 hour
    const CACHE_DURATION_LONG = 86400; // 24 hours

    /**
     * Get all schedules with caching
     */
    public static function getSchedules()
    {
        return Cache::remember('schedules:all', self::CACHE_DURATION_SHORT, function () {
            return \App\Models\Schedule::with(['vehicle', 'driver'])
                ->select('id', 'vehicle_id', 'driver_id', 'origin', 'destination', 'departure_time', 'estimated_duration', 'price', 'created_at')
                ->get();
        });
    }

    /**
     * Get all vehicles with caching
     */
    public static function getVehicles()
    {
        return Cache::remember('vehicles:all', self::CACHE_DURATION_MEDIUM, function () {
            return \App\Models\Vehicle::select('id', 'name', 'license_plate', 'capacity', 'created_at')->get();
        });
    }

    /**
     * Get dashboard statistics with short-term caching
     */
    public static function getDashboardStats()
    {
        return Cache::remember('dashboard:stats', self::CACHE_DURATION_SHORT, function () {
            return [
                'total_vehicles' => \App\Models\Vehicle::count(),
                'total_schedules' => \App\Models\Schedule::count(),
                'total_bookings' => \App\Models\Booking::count(),
                'total_users' => \App\Models\User::where('role', 'customer')->count(),
                'total_drivers' => \App\Models\User::where('role', 'driver')->count(),
                'active_trips' => \App\Models\Trip::where('status', 'on-going')->count(),
                'pending_bookings' => \App\Models\Booking::where('status', 'pending_payment')->count(),
                'completed_trips' => \App\Models\Trip::where('status', 'completed')->count(),
            ];
        });
    }

    /**
     * Get cached schedule by ID
     */
    public static function getScheduleById($scheduleId)
    {
        return Cache::remember("schedule:{$scheduleId}", self::CACHE_DURATION_SHORT, function () use ($scheduleId) {
            return \App\Models\Schedule::with(['vehicle', 'driver'])->find($scheduleId);
        });
    }

    /**
     * Get all drivers with caching
     */
    public static function getDrivers()
    {
        return Cache::remember('drivers:all', self::CACHE_DURATION_MEDIUM, function () {
            return \App\Models\User::where('role', 'driver')
                ->select('id', 'name', 'email', 'phone', 'created_at')
                ->get();
        });
    }

    /**
     * Clear specific cache key or all caches
     */
    public static function clearCache($pattern = null)
    {
        if ($pattern) {
            Cache::forget($pattern);
        } else {
            Cache::flush();
        }
    }

    /**
     * Clear multiple related caches
     */
    public static function clearRelatedCaches($patterns = [])
    {
        foreach ($patterns as $pattern) {
            if ($pattern) {
                self::clearCache($pattern);
            }
        }
    }

    /**
     * Invalidate cache after schedule creation/update
     */
    public static function invalidateScheduleCache($scheduleId = null)
    {
        $patterns = ['schedules:all', 'dashboard:stats'];
        if ($scheduleId) {
            $patterns[] = "schedule:{$scheduleId}";
        }
        self::clearRelatedCaches($patterns);
    }

    /**
     * Invalidate cache after booking changes
     */
    public static function invalidateBookingCache($scheduleId = null)
    {
        $patterns = ['dashboard:stats'];
        if ($scheduleId) {
            $patterns[] = "schedule:{$scheduleId}";
        }
        self::clearRelatedCaches($patterns);
    }

    /**
     * Invalidate cache after vehicle changes
     */
    public static function invalidateVehicleCache()
    {
        self::clearRelatedCaches(['vehicles:all', 'dashboard:stats']);
    }

    /**
     * Invalidate cache after driver changes
     */
    public static function invalidateDriverCache()
    {
        self::clearRelatedCaches(['drivers:all', 'dashboard:stats']);
    }
}
