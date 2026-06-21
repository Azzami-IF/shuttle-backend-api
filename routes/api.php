<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public auth routes (no throttle)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/admin/login', [\App\Http\Controllers\AdminApiController::class, 'adminLogin']);
Route::get('/payment-info', function() {
    return response()->json([
        'bank_name' => 'BNI',
        'account_number' => '1962757389',
        'account_holder' => 'NIRMALA FITRIA'
    ]);
});

// Stripe Webhook (public, no authentication required)
Route::post('/webhooks/stripe', [\App\Http\Controllers\PaymentController::class, 'webhook']);

// Database Seeding Helper for cPanel
Route::get('/seed-database', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed');
        return response()->json(['message' => 'Seeding completed successfully!']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Cache Clearing Helper for cPanel
Route::get('/clear-cache', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return response()->json([
            'message' => 'Cache cleared successfully!',
            'output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Database Migration Helper for cPanel
Route::get('/run-migration', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        return response()->json([
            'message' => 'Migration and Config Clear completed successfully!',
            'output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Debug helper for simulation on cPanel
Route::get('/debug-simulation', function(\Illuminate\Http\Request $request) {
    if ($request->query('token') !== 'sec-secret-123') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    $action = $request->query('action');
    
    if ($action === 'status') {
        $trips = \App\Models\Trip::with('schedule')->get();
        $bookings = \App\Models\Booking::with(['user', 'schedule'])->get();
        return response()->json([
            'trips' => $trips,
            'bookings' => $bookings
        ]);
    }
    
    if ($action === 'simulate') {
        try {
            \Illuminate\Support\Facades\Artisan::call('trips:simulate', [
                '--duration' => 5,
                '--interval' => 1
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'success' => true,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    if ($action === 'schedule') {
        try {
            \Illuminate\Support\Facades\Artisan::call('schedule:run');
            $output = \Illuminate\Support\Facades\Artisan::output();
            return response()->json([
                'success' => true,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    if ($action === 'logs') {
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            $logs = file_get_contents($logPath);
            $lines = explode("\n", $logs);
            $lastLines = array_slice($lines, -200);
            return response()->json([
                'logs' => implode("\n", $lastLines)
            ]);
        } else {
            return response()->json(['error' => 'Log file not found'], 404);
        }
    }
    
    return response()->json(['message' => 'Use action=status, action=simulate, action=schedule, or action=logs']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Read operations - 120 per minute
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('schedules', [\App\Http\Controllers\ScheduleController::class, 'index']);
        Route::get('schedules/{schedule}', [\App\Http\Controllers\ScheduleController::class, 'show']);
        Route::get('schedules/{schedule}/seats', [\App\Http\Controllers\ScheduleController::class, 'seats']);
        Route::get('bookings', [\App\Http\Controllers\BookingController::class, 'index']);
        Route::get('bookings/{booking}', [\App\Http\Controllers\BookingController::class, 'show']);
        Route::get('trips', [\App\Http\Controllers\TripController::class, 'index']);
        Route::get('trips/{trip}', [\App\Http\Controllers\TripController::class, 'show']);
        Route::get('trips/{trip}/location-history', [\App\Http\Controllers\TrackingController::class, 'history']);
    });

    // Write operations - 60 per minute
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('schedules', [\App\Http\Controllers\ScheduleController::class, 'store']);
        Route::post('bookings', [\App\Http\Controllers\BookingController::class, 'store']);
        Route::post('bookings/{booking}/cancel', [\App\Http\Controllers\BookingController::class, 'cancel']);
        Route::post('bookings/{booking}/upload-proof', [\App\Http\Controllers\BookingController::class, 'uploadProof']);
        Route::post('bookings/{booking}/confirm-payment', [\App\Http\Controllers\BookingController::class, 'confirmPayment']);
        Route::post('trips/{trip}/start', [\App\Http\Controllers\TripController::class, 'start']);
        Route::post('trips/{trip}/status', [\App\Http\Controllers\TripController::class, 'updateStatus']);
        Route::post('trips/{trip}/complete', [\App\Http\Controllers\TripController::class, 'complete']);

        // ============================================
        // PAYMENT ROUTES (Phase 3)
        // ============================================
        Route::post('payments/create-intent/{bookingId}', [\App\Http\Controllers\PaymentController::class, 'createPaymentIntent']);
        Route::post('payments/confirm/{bookingId}', [\App\Http\Controllers\PaymentController::class, 'confirmPayment']);
        Route::get('payments/status/{bookingId}', [\App\Http\Controllers\PaymentController::class, 'getPaymentStatus']);
        Route::post('refunds/request/{bookingId}', [\App\Http\Controllers\RefundController::class, 'requestRefund']);
        Route::get('refunds/status/{bookingId}', [\App\Http\Controllers\RefundController::class, 'getRefundStatus']);
        Route::get('invoices/{invoiceId}', [\App\Http\Controllers\InvoiceController::class, 'show']);
        Route::get('invoices', [\App\Http\Controllers\InvoiceController::class, 'getUserInvoices']);
        Route::post('invoices/{invoiceId}/send-email', [\App\Http\Controllers\InvoiceController::class, 'sendEmail']);
    });

    // Tracking/Location updates - 300 per minute
    Route::middleware('throttle:300,1')->group(function () {
        Route::post('trips/{trip}/location', [\App\Http\Controllers\TrackingController::class, 'update']);
        Route::get('trips/{trip}/latest-location', [\App\Http\Controllers\TrackingController::class, 'latest']);
    });

    // Vehicle Management (Admin only usually, but open for demo)
    Route::middleware('throttle:60,1')->group(function () {
        Route::apiResource('vehicles', \App\Http\Controllers\VehicleController::class);
    });

    // ============================================
    // ADMIN API ROUTES - All require authentication
    // ============================================
    Route::prefix('admin')->group(function () {
        // Read operations - 120 per minute
        Route::middleware('throttle:120,1')->group(function () {
            // Dashboard Analytics
            Route::get('/dashboard/stats', [\App\Http\Controllers\AdminApiController::class, 'dashboardStats']);
            Route::get('/dashboard/bookings', [\App\Http\Controllers\AdminApiController::class, 'dashboardBookings']);
            Route::get('/dashboard/revenue', [\App\Http\Controllers\AdminApiController::class, 'dashboardRevenue']);
            Route::get('/dashboard/drivers', [\App\Http\Controllers\AdminApiController::class, 'dashboardDrivers']);
            Route::get('/dashboard/vehicles', [\App\Http\Controllers\AdminApiController::class, 'dashboardVehicles']);

            // User Management - Read
            Route::get('/users', [\App\Http\Controllers\AdminApiController::class, 'listUsers']);
            Route::get('/users/{userId}', [\App\Http\Controllers\AdminApiController::class, 'getUser']);

            // Driver Management - Read
            Route::get('/drivers', [\App\Http\Controllers\AdminApiController::class, 'listDrivers']);

            // Vehicle Management - Read
            Route::get('/vehicles', [\App\Http\Controllers\AdminApiController::class, 'listVehicles']);

            // Schedule Management - Read
            Route::get('/schedules', [\App\Http\Controllers\AdminApiController::class, 'listSchedules']);

            // Booking Management - Read
            Route::get('/bookings', [\App\Http\Controllers\AdminApiController::class, 'listBookings']);
            Route::get('/bookings/{bookingId}', [\App\Http\Controllers\AdminApiController::class, 'getBooking']);

            // Trip Management - Read
            Route::get('/trips', [\App\Http\Controllers\AdminApiController::class, 'listTrips']);
            Route::get('/trips/{tripId}', [\App\Http\Controllers\AdminApiController::class, 'getTrip']);

            // Reports & Analytics
            Route::get('/reports/daily', [\App\Http\Controllers\AdminApiController::class, 'dailyReport']);
            Route::get('/reports/monthly', [\App\Http\Controllers\AdminApiController::class, 'monthlyReport']);

            // System Monitoring
            Route::get('/system/health', [\App\Http\Controllers\AdminApiController::class, 'systemHealth']);
            Route::get('/system/logs', [\App\Http\Controllers\AdminApiController::class, 'systemLogs']);

            // Audit Logs
            Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index']);
            Route::get('/audit-logs/{auditLog}', [\App\Http\Controllers\AuditLogController::class, 'show']);
            Route::get('/audit-logs/statistics', [\App\Http\Controllers\AuditLogController::class, 'statistics']);
        });

        // Write operations - 60 per minute
        Route::middleware('throttle:60,1')->group(function () {
            // User Management - Write
            Route::post('/users', [\App\Http\Controllers\AdminApiController::class, 'createUser']);
            Route::put('/users/{userId}', [\App\Http\Controllers\AdminApiController::class, 'updateUser']);
            Route::delete('/users/{userId}', [\App\Http\Controllers\AdminApiController::class, 'deleteUser']);

            // Driver Management - Write
            Route::put('/drivers/{driverId}/approve', [\App\Http\Controllers\AdminApiController::class, 'approveDriver']);

            // Vehicle Management - Write
            Route::post('/vehicles', [\App\Http\Controllers\AdminApiController::class, 'createVehicle']);
            Route::put('/vehicles/{vehicleId}', [\App\Http\Controllers\AdminApiController::class, 'updateVehicle']);
            Route::delete('/vehicles/{vehicleId}', [\App\Http\Controllers\AdminApiController::class, 'deleteVehicle']);

            // Schedule Management - Write
            Route::post('/schedules', [\App\Http\Controllers\AdminApiController::class, 'createSchedule']);
            Route::delete('/schedules/{scheduleId}', [\App\Http\Controllers\AdminApiController::class, 'deleteSchedule']);

            // Booking Management - Write
            Route::post('/bookings/{bookingId}/approve', [\App\Http\Controllers\AdminApiController::class, 'approveBooking']);
            Route::post('/bookings/{bookingId}/cancel', [\App\Http\Controllers\AdminApiController::class, 'cancelBooking']);

            // Payment Management - Write (Admin only)
            Route::post('/invoices/{invoiceId}/mark-paid', [\App\Http\Controllers\InvoiceController::class, 'markAsPaid']);
        });
    });
});
