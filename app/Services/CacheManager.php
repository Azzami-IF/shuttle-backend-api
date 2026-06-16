<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheManager
{
    const CACHE_DURATION_SHORT = 300; // 5 minutes
    const CACHE_DURATION_MEDIUM = 3600; // 1 hour

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
     * Get all schedules with caching
     */
    public static function getSchedules()
    {
        return Cache::remember('schedules:all', self::CACHE_DURATION_SHORT, function () {
            return \App\Models\Schedule::with(['vehicle', 'driver'])
                ->select('id', 'vehicle_id', 'driver_id', 'origin', 'destination', 'departure_time', 'created_at')
                ->get();
        });
    }

    /**
     * Invalidate schedule-related cache keys
     */
    public static function invalidateScheduleCache($scheduleId = null)
    {
        Cache::forget('schedules:all');
        Cache::forget('dashboard:stats');
        if ($scheduleId) {
            Cache::forget("schedule_{$scheduleId}_seats");
            Cache::forget("schedule_{$scheduleId}_bookings");
        }
    }

    public static function invalidateVehicleCache()
    {
        Cache::forget('dashboard:stats');
    }

    public static function invalidateDriverCache()
    {
        Cache::forget('dashboard:stats');
    }

    /**
     * Invalidate booking cache for a schedule
     */
    public static function invalidateBookingCache($scheduleId)
    {
        try {
            Cache::forget("schedule_{$scheduleId}_seats");
            Cache::forget("schedule_{$scheduleId}_bookings");
        } catch (\Exception $e) {
            // Log error but don't fail - caching is optional
            \Log::warning("Failed to invalidate cache: " . $e->getMessage());
        }
    }

    /**
     * Get cached schedule with seats
     */
    public static function getScheduleSeats($scheduleId)
    {
        $cacheKey = "schedule_{$scheduleId}_seats";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($scheduleId) {
            return \App\Models\Seat::where('schedule_id', $scheduleId)->get();
        });
    }

    /**
     * Warm up cache for popular schedules
     */
    public static function warmScheduleCache($scheduleIds = [])
    {
        foreach ($scheduleIds as $scheduleId) {
            self::getScheduleSeats($scheduleId);
        }
    }
}
