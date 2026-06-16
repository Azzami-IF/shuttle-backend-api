<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Schedule;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\Location;
use App\Services\CacheManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminApiController extends Controller
{
    /**
     * PAGINATION CONSTANTS
     * These constants ensure consistent and optimized pagination across all list endpoints
     */
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /**
     * AUTHENTICATION & AUTHORIZATION
     */

    /**
     * Admin login - only for users with admin role
     */
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        // Check if user is admin
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return response()->json([
                'message' => 'Unauthorized: Admin access required',
            ], 403);
        }

        return response()->json([
            'message' => 'Admin logged in successfully',
            'user' => $user,
            'token' => $user->createToken('admin_token')->plainTextToken,
        ]);
    }

    /**
     * Check admin authorization middleware equivalent
     */
    protected function checkAdminRole(Request $request)
    {
        $user = $request->user();
        if (!$user || ($user->role !== 'admin' && $user->role !== 'superadmin')) {
            abort(403, 'Unauthorized: Admin access required');
        }
    }

    /**
     * DASHBOARD ANALYTICS
     */

    /**
     * GET /admin/dashboard/stats - Overall statistics
     */
    public function dashboardStats(Request $request)
    {
        $this->checkAdminRole($request);
        return response()->json(CacheManager::getDashboardStats());
    }

    /**
     * GET /admin/dashboard/bookings - Booking analytics
     */
    public function dashboardBookings(Request $request)
    {
        $this->checkAdminRole($request);

        $days = $request->get('days', 7);
        
        // Use proper database aggregation instead of loading all records
        $bookingStats = Booking::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "booked" THEN 1 ELSE 0 END) as completed'),
            DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            DB::raw('SUM(CASE WHEN status = "pending_payment" THEN 1 ELSE 0 END) as pending')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period_days' => $days,
            'data' => $bookingStats,
        ]);
    }

    /**
     * GET /admin/dashboard/revenue - Revenue reports (when payment system is ready)
     */
    public function dashboardRevenue(Request $request)
    {
        $this->checkAdminRole($request);

        $days = $request->get('days', 30);

        $revenueStats = DB::table('payments')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as bookings_count'),
                DB::raw('COALESCE(SUM(amount), 0) as revenue')
            )
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period_days' => $days,
            'total_revenue' => $revenueStats->sum('revenue'),
            'total_bookings' => $revenueStats->sum('bookings_count'),
            'daily_data' => $revenueStats,
        ]);
    }

    /**
     * GET /admin/dashboard/drivers - Driver statistics
     */
    public function dashboardDrivers(Request $request)
    {
        $this->checkAdminRole($request);

        $drivers = User::where('role', 'driver')
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->withCount('schedules')
            ->get()
            ->map(function ($driver) {
                $completedTrips = Trip::whereHas('schedule', function ($q) use ($driver) {
                    $q->where('driver_id', $driver->id);
                })->where('status', 'completed')->count();

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'email' => $driver->email,
                    'phone' => $driver->phone,
                    'schedules_count' => $driver->schedules_count,
                    'completed_trips' => $completedTrips,
                    'joined_at' => $driver->created_at,
                ];
            });

        return response()->json([
            'total_drivers' => count($drivers),
            'drivers' => $drivers,
        ]);
    }

    /**
     * GET /admin/dashboard/vehicles - Vehicle status
     */
    public function dashboardVehicles(Request $request)
    {
        $this->checkAdminRole($request);

        $vehicles = Vehicle::with('schedules')
            ->select('id', 'name', 'license_plate', 'capacity', 'created_at')
            ->get()
            ->map(function ($vehicle) {
                $activeSchedules = Schedule::where('vehicle_id', $vehicle->id)
                    ->where('departure_time', '>', now())
                    ->count();

                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                    'capacity' => $vehicle->capacity,
                    'total_schedules' => $vehicle->schedules_count ?? 0,
                    'upcoming_schedules' => $activeSchedules,
                    'created_at' => $vehicle->created_at,
                ];
            });

        return response()->json([
            'total_vehicles' => count($vehicles),
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * USER MANAGEMENT
     */

    /**
     * GET /admin/users - List all users with filtering & pagination
     */
    public function listUsers(Request $request)
    {
        $this->checkAdminRole($request);

        $query = User::query();

        // Filtering
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Pagination with optimization
        $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        
        // Select only necessary columns to reduce data transfer
        $users = $query->select('id', 'name', 'email', 'phone', 'role', 'created_at')
            ->paginate($perPage);

        return response()->json($users);
    }

    /**
     * GET /admin/users/{userId} - Get single user details
     */
    public function getUser(Request $request, $userId)
    {
        $this->checkAdminRole($request);

        $user = User::findOrFail($userId);

        return response()->json($user);
    }

    /**
     * POST /admin/users - Create new user/admin
     */
    public function createUser(Request $request)
    {
        $this->checkAdminRole($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:customer,driver,admin',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * PUT /admin/users/{userId} - Update user
     */
    public function updateUser(Request $request, $userId)
    {
        $this->checkAdminRole($request);

        $user = User::findOrFail($userId);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|required|in:customer,driver,admin',
        ]);

        $user->update($request->only(['name', 'email', 'phone', 'role']));

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * DELETE /admin/users/{userId} - Delete user
     */
    public function deleteUser(Request $request, $userId)
    {
        $this->checkAdminRole($request);

        $user = User::findOrFail($userId);
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * DRIVER MANAGEMENT
     */

    /**
     * GET /admin/drivers - List all drivers
     */
    public function listDrivers(Request $request)
    {
        $this->checkAdminRole($request);

        $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        
        $drivers = User::where('role', 'driver')
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->paginate($perPage);

        return response()->json($drivers);
    }

    /**
     * PUT /admin/drivers/{driverId}/approve - Approve driver
     */
    public function approveDriver(Request $request, $driverId)
    {
        $this->checkAdminRole($request);

        $driver = User::where('role', 'driver')->findOrFail($driverId);
        
        // Add approval status if not exists
        $driver->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Driver approved successfully',
            'driver' => $driver,
        ]);
    }

    /**
     * VEHICLE MANAGEMENT
     */

    /**
     * GET /admin/vehicles - List all vehicles
     */
    public function listVehicles(Request $request)
    {
        $this->checkAdminRole($request);

        $query = Vehicle::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('license_plate', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        
        // Select only necessary columns to reduce data transfer
        $vehicles = $query->select('id', 'name', 'license_plate', 'capacity', 'created_at')
            ->paginate($perPage);

        return response()->json($vehicles);
    }

    /**
     * POST /admin/vehicles - Create vehicle
     */
    public function createVehicle(Request $request)
    {
        $this->checkAdminRole($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'license_plate' => 'required|string|unique:vehicles',
            'capacity' => 'required|integer|min:1',
        ]);

        $vehicle = Vehicle::create($request->only(['name', 'license_plate', 'capacity']));

        return response()->json([
            'message' => 'Vehicle created successfully',
            'vehicle' => $vehicle,
        ], 201);
    }

    /**
     * PUT /admin/vehicles/{vehicleId} - Update vehicle
     */
    public function updateVehicle(Request $request, $vehicleId)
    {
        $this->checkAdminRole($request);

        $vehicle = Vehicle::findOrFail($vehicleId);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'license_plate' => 'sometimes|required|string|unique:vehicles,license_plate,' . $vehicle->id,
            'capacity' => 'sometimes|required|integer|min:1',
        ]);

        $vehicle->update($request->only(['name', 'license_plate', 'capacity']));

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'vehicle' => $vehicle,
        ]);
    }

    /**
     * DELETE /admin/vehicles/{vehicleId} - Delete vehicle
     */
    public function deleteVehicle(Request $request, $vehicleId)
    {
        $this->checkAdminRole($request);

        $vehicle = Vehicle::findOrFail($vehicleId);
        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully',
        ]);
    }

    /**
     * SCHEDULE MANAGEMENT
     */

    /**
     * GET /admin/schedules - List all schedules (optimized with selective column loading)
     */
    public function listSchedules(Request $request)
    {
        $this->checkAdminRole($request);

        if ($request->has('search') && $request->search) {
            // Don't cache search results
            $search = $request->search;
            $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
            
            $query = Schedule::with([
                'vehicle:id,name,license_plate',
                'driver:id,name,phone'
            ])
                ->select('id', 'vehicle_id', 'driver_id', 'origin', 'destination', 'departure_time', 'created_at')
                ->where(function ($q) use ($search) {
                    $q->where('origin', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%");
                })
                ->paginate($perPage);
            
            return response()->json($query);
        }

        // Cache full list
        $schedules = CacheManager::getSchedules();
        return response()->json($schedules);
    }

    /**
     * POST /admin/schedules - Create schedule
     */
    public function createSchedule(Request $request)
    {
        $this->checkAdminRole($request);

        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:users,id',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'departure_time' => 'required|date',
            'repeat_days' => 'nullable|integer|min:1|max:30',
        ]);

        try {
            $repeatDays = $request->get('repeat_days', 1);
            $schedules = [];

            DB::transaction(function () use ($request, $repeatDays, &$schedules) {
                $baseTime = new \Illuminate\Support\Carbon($request->departure_time);

                for ($i = 0; $i < $repeatDays; $i++) {
                    $departureTime = $baseTime->copy()->addDays($i);

                    $schedule = Schedule::create([
                        'vehicle_id' => $request->vehicle_id,
                        'driver_id' => $request->driver_id,
                        'origin' => $request->origin,
                        'destination' => $request->destination,
                        'departure_time' => $departureTime,
                    ]);

                    // Create seats based on vehicle capacity
                    $vehicle = Vehicle::find($request->vehicle_id);
                    for ($j = 1; $j <= $vehicle->capacity; $j++) {
                        \App\Models\Seat::create([
                            'schedule_id' => $schedule->id,
                            'seat_number' => (string)$j,
                            'status' => 'available',
                        ]);
                    }

                    // Create trip record
                    Trip::create([
                        'schedule_id' => $schedule->id,
                        'status' => 'scheduled',
                    ]);

                    $schedules[] = $schedule;
                }
            });

            return response()->json([
                'message' => $repeatDays > 1 ? "Created $repeatDays schedules successfully" : 'Schedule created successfully',
                'schedules' => $schedules,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create schedule(s)',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Invalidate related caches
        CacheManager::invalidateScheduleCache($schedule->id ?? null);
    }

    /**
     * DELETE /admin/schedules/{scheduleId} - Delete schedule
     */
    public function deleteSchedule(Request $request, $scheduleId)
    {
        $this->checkAdminRole($request);

        $schedule = Schedule::findOrFail($scheduleId);
        $schedule->delete();

        // Invalidate related caches
        CacheManager::invalidateScheduleCache($scheduleId);

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }

    /**
     * BOOKING MANAGEMENT
     */

    /**
     * GET /admin/bookings - List all bookings with filtering
     */
    public function listBookings(Request $request)
    {
        $this->checkAdminRole($request);

        $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        
        $query = Booking::with(['user', 'schedule', 'seat'])
            ->select('id', 'user_id', 'schedule_id', 'seat_id', 'status', 'created_at');

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('schedule', function ($sq) use ($search) {
                    $sq->where('origin', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%");
                });
            });
        }

        $bookings = $query->latest()->paginate($perPage);

        return response()->json($bookings);
    }

    /**
     * GET /admin/bookings/{bookingId} - Get booking details
     */
    public function getBooking(Request $request, $bookingId)
    {
        $this->checkAdminRole($request);

        $booking = Booking::with(['user', 'schedule', 'seat'])->findOrFail($bookingId);

        return response()->json($booking);
    }

    /**
     * POST /admin/bookings/{bookingId}/approve - Approve pending booking
     */
    public function approveBooking(Request $request, $bookingId)
    {
        $this->checkAdminRole($request);

        $booking = Booking::findOrFail($bookingId);

        if ($booking->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Only pending payment bookings can be approved',
            ], 400);
        }

        $booking->update(['status' => 'booked']);

        return response()->json([
            'message' => 'Booking approved successfully',
            'booking' => $booking,
        ]);
    }

    /**
     * POST /admin/bookings/{bookingId}/cancel - Cancel booking
     */
    public function cancelBooking(Request $request, $bookingId)
    {
        $this->checkAdminRole($request);

        $booking = Booking::findOrFail($bookingId);

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json([
                'message' => 'Cannot cancel completed or already cancelled bookings',
            ], 400);
        }

        $booking->update(['status' => 'cancelled']);

        // Return seat to available
        if ($booking->seat) {
            $booking->seat->update(['status' => 'available']);
        }

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $booking,
        ]);
    }

    /**
     * TRIP MANAGEMENT
     */

    /**
     * GET /admin/trips - List all trips
     */
    public function listTrips(Request $request)
    {
        $this->checkAdminRole($request);

        $perPage = min($request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        
        $query = Trip::with(['schedule.vehicle', 'schedule.driver'])
            ->select('id', 'schedule_id', 'status', 'created_at');

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $trips = $query->latest()->paginate($perPage);

        return response()->json($trips);
    }

    /**
     * GET /admin/trips/{tripId} - Get trip details
     */
    public function getTrip(Request $request, $tripId)
    {
        $this->checkAdminRole($request);

        $trip = Trip::with(['schedule.vehicle', 'schedule.driver', 'locations'])->findOrFail($tripId);

        return response()->json($trip);
    }

    /**
     * REPORTING & ANALYTICS
     */

    /**
     * GET /admin/reports/daily - Daily report
     */
    public function dailyReport(Request $request)
    {
        $this->checkAdminRole($request);

        $date = $request->get('date', now()->format('Y-m-d'));

        $report = [
            'date' => $date,
            'total_bookings' => Booking::whereDate('created_at', $date)->count(),
            'completed_bookings' => Booking::whereDate('created_at', $date)->where('status', 'booked')->count(),
            'cancelled_bookings' => Booking::whereDate('created_at', $date)->where('status', 'cancelled')->count(),
            'total_trips' => Trip::whereDate('created_at', $date)->count(),
            'completed_trips' => Trip::whereDate('created_at', $date)->where('status', 'completed')->count(),
            'active_trips' => Trip::whereDate('created_at', $date)->where('status', 'on-going')->count(),
        ];

        return response()->json($report);
    }

    /**
     * GET /admin/reports/monthly - Monthly report
     */
    public function monthlyReport(Request $request)
    {
        $this->checkAdminRole($request);

        $month = $request->get('month', now()->format('Y-m'));

        $report = [
            'month' => $month,
            'total_bookings' => Booking::whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))->count(),
            'completed_bookings' => Booking::whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))
                ->where('status', 'booked')->count(),
            'total_trips' => Trip::whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))->count(),
            'completed_trips' => Trip::whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))
                ->where('status', 'completed')->count(),
            'new_drivers' => User::where('role', 'driver')
                ->whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))->count(),
            'new_users' => User::where('role', 'customer')
                ->whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2))->count(),
        ];

        return response()->json($report);
    }

    /**
     * SYSTEM MONITORING
     */

    /**
     * GET /admin/system/health - System health check
     */
    public function systemHealth(Request $request)
    {
        $this->checkAdminRole($request);

        try {
            DB::connection()->getPdo();
            $dbStatus = 'healthy';
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }

        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'database' => $dbStatus,
            'api' => 'healthy',
        ]);
    }

    /**
     * GET /admin/system/logs - Activity logs
     */
    public function systemLogs(Request $request)
    {
        $this->checkAdminRole($request);

        // Note: This is a placeholder. Implement proper audit logging.
        $logs = [
            [
                'id' => 1,
                'action' => 'Admin logged in',
                'user_id' => $request->user()->id,
                'timestamp' => now(),
            ],
        ];

        return response()->json([
            'total' => count($logs),
            'logs' => $logs,
        ]);
    }
}
