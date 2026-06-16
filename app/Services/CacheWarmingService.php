<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cache Warming Service
 * 
 * Populates cache with frequently accessed data:
 * - Startup cache warming
 * - Background refresh jobs
 * - Hot data pre-loading
 * - Cache warming metrics
 */
class CacheWarmingService
{
    private array $stats = [
        'total_queries' => 0,
        'successful' => 0,
        'failed' => 0,
        'execution_time' => 0,
    ];

    /**
     * Warm all essential caches on application startup
     */
    public function warmAll(): array
    {
        $startTime = microtime(true);
        
        Log::info("Starting comprehensive cache warming");

        // Warm critical queries
        $this->warmDashboardMetrics();
        $this->warmActiveBookings();
        $this->warmActiveDrivers();
        $this->warmVehicleStatus();
        $this->warmScheduleData();
        $this->warmAnalyticsData();

        $this->stats['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);

        Log::info("Cache warming completed", $this->stats);
        
        return $this->stats;
    }

    /**
     * Warm dashboard metrics cache
     */
    private function warmDashboardMetrics(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            // Total bookings today
            $todayBookings = Booking::whereDate('created_at', today())->count();
            Cache::tags(['dashboard', 'bookings'])
                ->put('dashboard:today_bookings', $todayBookings, 300);

            // Revenue today
            $todayRevenue = \DB::table('payments')
                ->whereDate('created_at', today())
                ->sum('amount');
            Cache::tags(['dashboard', 'revenue'])
                ->put('dashboard:today_revenue', $todayRevenue, 300);

            // Active drivers
            $activeDrivers = Driver::where('status', 'active')->count();
            Cache::tags(['dashboard', 'drivers'])
                ->put('dashboard:active_drivers', $activeDrivers, 600);

            // Available vehicles
            $availableVehicles = Vehicle::where('status', 'available')->count();
            Cache::tags(['dashboard', 'vehicles'])
                ->put('dashboard:available_vehicles', $availableVehicles, 600);

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm active bookings cache
     */
    private function warmActiveBookings(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            $bookings = Booking::with(['user', 'driver', 'vehicle'])
                ->whereIn('status', ['pending', 'confirmed', 'in_transit'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            Cache::tags(['bookings', 'active'])
                ->put('bookings:active', $bookings, 60);

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm active drivers cache
     */
    private function warmActiveDrivers(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            $drivers = Driver::with('vehicle', 'ratings')
                ->where('status', 'active')
                ->where('available', true)
                ->orderBy('rating', 'desc')
                ->limit(50)
                ->get();

            Cache::tags(['drivers', 'active'])
                ->put('drivers:active', $drivers, 300);

            // Also cache by location for faster lookup
            $byLocation = $drivers->groupBy('city');
            foreach ($byLocation as $city => $driverGroup) {
                Cache::tags(['drivers', 'location'])
                    ->put("drivers:active:{$city}", $driverGroup, 300);
            }

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm vehicle status cache
     */
    private function warmVehicleStatus(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            $vehicles = Vehicle::with('driver', 'maintenance_schedule')
                ->get();

            // Cache vehicles by status
            foreach ($vehicles->groupBy('status') as $status => $statusVehicles) {
                Cache::tags(['vehicles', 'status'])
                    ->put("vehicles:by_status:{$status}", $statusVehicles, 3600);
            }

            // Cache all vehicles for quick access
            Cache::tags(['vehicles'])
                ->put('vehicles:all', $vehicles, 3600);

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm schedule data cache
     */
    private function warmScheduleData(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            // Active schedules for today
            $todaySchedules = Schedule::where('date', today())
                ->with('driver', 'vehicle', 'bookings')
                ->get();

            Cache::tags(['schedules'])
                ->put('schedules:today', $todaySchedules, 1800);

            // Upcoming schedules (next 7 days)
            $upcomingSchedules = Schedule::whereBetween('date', [today(), today()->addDays(7)])
                ->orderBy('date')
                ->get();

            Cache::tags(['schedules'])
                ->put('schedules:upcoming', $upcomingSchedules, 3600);

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm analytics data cache
     */
    private function warmAnalyticsData(): void
    {
        $this->logQuery(__FUNCTION__);

        try {
            // Daily bookings trend (last 30 days)
            $dailyTrend = \DB::table('bookings')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [now()->subDays(30), now()])
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            Cache::tags(['analytics', 'bookings'])
                ->put('analytics:daily_trend', $dailyTrend, 3600);

            // Revenue by city (last 30 days)
            $revenueByCity = \DB::table('bookings')
                ->join('payments', 'bookings.id', 'payments.booking_id')
                ->selectRaw('bookings.destination_city as city, SUM(payments.amount) as revenue')
                ->whereBetween('payments.created_at', [now()->subDays(30), now()])
                ->groupBy('destination_city')
                ->orderBy('revenue', 'desc')
                ->get();

            Cache::tags(['analytics', 'revenue'])
                ->put('analytics:revenue_by_city', $revenueByCity, 3600);

            // Top drivers by rating
            $topDrivers = Driver::orderBy('rating', 'desc')
                ->limit(20)
                ->get(['id', 'name', 'rating', 'total_trips']);

            Cache::tags(['analytics', 'drivers'])
                ->put('analytics:top_drivers', $topDrivers, 3600);

            $this->logSuccess(__FUNCTION__);
        } catch (\Exception $e) {
            $this->logError(__FUNCTION__, $e);
        }
    }

    /**
     * Warm cache for a specific entity
     */
    public function warmEntity(string $entity, array $options = []): array
    {
        $stats = ['entity' => $entity, 'status' => 'started'];
        
        try {
            match ($entity) {
                'bookings' => $this->warmActiveBookings(),
                'drivers' => $this->warmActiveDrivers(),
                'vehicles' => $this->warmVehicleStatus(),
                'schedules' => $this->warmScheduleData(),
                'analytics' => $this->warmAnalyticsData(),
                default => throw new \InvalidArgumentException("Unknown entity: {$entity}"),
            };

            $stats['status'] = 'completed';
        } catch (\Exception $e) {
            $stats['status'] = 'failed';
            $stats['error'] = $e->getMessage();
            Log::error("Failed to warm cache for {$entity}", ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * Get cache warming statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Invalidate warming caches for refresh
     */
    public function invalidateAll(): void
    {
        Cache::tags([
            'dashboard', 'bookings', 'drivers', 'vehicles',
            'schedules', 'analytics', 'active', 'location', 'status'
        ])->flush();

        Log::info("All warming caches invalidated");
    }

    /**
     * Log a query attempt
     */
    private function logQuery(string $method): void
    {
        $this->stats['total_queries']++;
    }

    /**
     * Log successful query
     */
    private function logSuccess(string $method): void
    {
        $this->stats['successful']++;
        Log::debug("Cache warming success", ['method' => $method]);
    }

    /**
     * Log failed query
     */
    private function logError(string $method, \Exception $e): void
    {
        $this->stats['failed']++;
        Log::error("Cache warming failed", ['method' => $method, 'error' => $e->getMessage()]);
    }
}
