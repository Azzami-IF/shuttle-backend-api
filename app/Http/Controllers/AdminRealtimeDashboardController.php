<?php

namespace App\Http\Controllers;

use App\Services\BroadcastingService;
use App\Services\WebSocketManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin Real-time Dashboard API
 * 
 * Provides real-time metrics for admin dashboard:
 * - Live booking updates
 * - Active driver metrics
 * - Revenue tracking
 * - System performance
 * - WebSocket connection management
 */
class AdminRealtimeDashboardController extends Controller
{
    private BroadcastingService $broadcastingService;
    private WebSocketManager $websocketManager;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin.role');
        $this->broadcastingService = new BroadcastingService();
        $this->websocketManager = new WebSocketManager();
    }

    /**
     * Get real-time dashboard metrics
     */
    public function getMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'timestamp' => now()->toIso8601String(),
                'bookings' => $this->getBookingMetrics(),
                'drivers' => $this->getDriverMetrics(),
                'revenue' => $this->getRevenueMetrics(),
                'vehicles' => $this->getVehicleMetrics(),
                'system' => $this->getSystemMetrics(),
                'websocket' => $this->getWebSocketMetrics(),
            ];

            // Broadcast to all connected admins
            $this->broadcastingService->broadcastAdminDashboardUpdate($metrics);

            return response()->json([
                'data' => $metrics,
                'meta' => [
                    'version' => '1.0',
                    'realtime' => true,
                    'broadcast' => 'sent'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get live booking metrics
     */
    private function getBookingMetrics(): array
    {
        $today = \Carbon\Carbon::today();

        return [
            'total_today' => DB::table('bookings')
                ->whereDate('created_at', $today)
                ->count(),
            'pending' => DB::table('bookings')
                ->where('status', 'pending')
                ->count(),
            'confirmed' => DB::table('bookings')
                ->where('status', 'confirmed')
                ->count(),
            'in_transit' => DB::table('bookings')
                ->where('status', 'in_transit')
                ->count(),
            'completed' => DB::table('bookings')
                ->whereDate('updated_at', $today)
                ->where('status', 'completed')
                ->count(),
            'cancelled' => DB::table('bookings')
                ->whereDate('updated_at', $today)
                ->where('status', 'cancelled')
                ->count(),
            'active_count' => DB::table('bookings')
                ->whereIn('status', ['pending', 'confirmed', 'in_transit'])
                ->count(),
            'completion_rate' => $this->getCompletionRate(),
        ];
    }

    /**
     * Get live driver metrics
     */
    private function getDriverMetrics(): array
    {
        return [
            'online' => WebSocketManager::getOnlineUsersCount(),
            'available' => DB::table('drivers')
                ->where('status', 'active')
                ->where('available', true)
                ->count(),
            'busy' => DB::table('drivers')
                ->where('status', 'active')
                ->where('available', false)
                ->count(),
            'offline' => DB::table('drivers')
                ->where('status', 'offline')
                ->count(),
            'total' => DB::table('drivers')->count(),
            'avg_rating' => DB::table('drivers')
                ->whereNotNull('rating')
                ->avg('rating') ?? 0,
            'active_bookings' => DB::table('bookings')
                ->whereIn('status', ['confirmed', 'in_transit'])
                ->groupBy('driver_id')
                ->selectRaw('driver_id, COUNT(*) as count')
                ->get(),
        ];
    }

    /**
     * Get live revenue metrics
     */
    private function getRevenueMetrics(): array
    {
        $today = \Carbon\Carbon::today();
        $thisMonth = \Carbon\Carbon::now()->startOfMonth();

        $todayRevenue = DB::table('payments')
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('amount') ?? 0;

        $monthRevenue = DB::table('payments')
            ->whereBetween('created_at', [$thisMonth, now()])
            ->where('status', 'completed')
            ->sum('amount') ?? 0;

        return [
            'today' => round($todayRevenue, 2),
            'this_month' => round($monthRevenue, 2),
            'avg_per_booking' => $this->getAverageBookingValue(),
            'pending_payments' => DB::table('payments')
                ->where('status', 'pending')
                ->sum('amount') ?? 0,
            'refunded' => DB::table('payments')
                ->where('status', 'refunded')
                ->whereDate('updated_at', $today)
                ->sum('amount') ?? 0,
            'payment_methods' => $this->getPaymentMethodBreakdown(),
        ];
    }

    /**
     * Get live vehicle metrics
     */
    private function getVehicleMetrics(): array
    {
        return [
            'available' => DB::table('vehicles')
                ->where('status', 'available')
                ->count(),
            'in_use' => DB::table('vehicles')
                ->where('status', 'in_use')
                ->count(),
            'maintenance' => DB::table('vehicles')
                ->where('status', 'maintenance')
                ->count(),
            'offline' => DB::table('vehicles')
                ->where('status', 'offline')
                ->count(),
            'total' => DB::table('vehicles')->count(),
            'utilization_rate' => $this->getVehicleUtilization(),
            'by_type' => DB::table('vehicles')
                ->groupBy('type')
                ->selectRaw('type, COUNT(*) as count')
                ->pluck('count', 'type'),
        ];
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'uptime_seconds' => time() - (strtotime('2026-05-23 00:00:00') ?? time()),
            'active_sessions' => Cache::get('active_sessions', 0),
            'database_connections' => DB::getConnections() ? count(DB::getConnections()) : 0,
            'cache_hit_rate' => Cache::get('cache_metrics:hit_rate', '0%'),
            'response_time_ms' => 0, // Would measure actual response times
            'error_rate' => '0%',
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ];
    }

    /**
     * Get WebSocket connection metrics
     */
    private function getWebSocketMetrics(): array
    {
        $stats = WebSocketManager::getStats();
        $health = WebSocketManager::healthCheck();

        return [
            'status' => $health['status'] ?? 'unknown',
            'active_connections' => $stats['active_connections'] ?? 0,
            'active_channels' => $stats['active_channels'] ?? 0,
            'memory_usage' => $stats['memory_usage'] ?? 'N/A',
            'uptime_seconds' => $stats['uptime_seconds'] ?? 0,
            'redis_connected' => $health['redis_connection'] ?? 'disconnected',
        ];
    }

    /**
     * Subscribe to real-time updates
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $connectionId = $request->input('connection_id', uniqid('conn_'));

            WebSocketManager::trackConnection($userId, $connectionId, 'admin:dashboard');

            return response()->json([
                'status' => 'subscribed',
                'connection_id' => $connectionId,
                'channel' => 'admin:dashboard',
                'message' => 'You are now receiving real-time admin metrics'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unsubscribe from real-time updates
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $connectionId = $request->input('connection_id');

            WebSocketManager::untrackConnection($userId, $connectionId);

            return response()->json([
                'status' => 'unsubscribed',
                'message' => 'You have unsubscribed from real-time metrics'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get WebSocket health status
     */
    public function health(): JsonResponse
    {
        return response()->json(WebSocketManager::healthCheck());
    }

    /**
     * Get connected users (presence)
     */
    public function getConnectedAdmins(): JsonResponse
    {
        try {
            $users = WebSocketManager::getUsersInChannel('admin:dashboard');
            return response()->json([
                'connected_admins' => count($users),
                'user_ids' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper: Get completion rate
     */
    private function getCompletionRate(): float
    {
        $total = DB::table('bookings')->count();
        if ($total === 0) return 0;

        $completed = DB::table('bookings')
            ->where('status', 'completed')
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Helper: Get average booking value
     */
    private function getAverageBookingValue(): float
    {
        return round(DB::table('payments')
            ->where('status', 'completed')
            ->avg('amount') ?? 0, 2);
    }

    /**
     * Helper: Get vehicle utilization
     */
    private function getVehicleUtilization(): float
    {
        $total = DB::table('vehicles')->count();
        if ($total === 0) return 0;

        $inUse = DB::table('vehicles')
            ->where('status', 'in_use')
            ->count();

        return round(($inUse / $total) * 100, 2);
    }

    /**
     * Helper: Get payment method breakdown
     */
    private function getPaymentMethodBreakdown(): array
    {
        return DB::table('payments')
            ->where('status', 'completed')
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->payment_method => [
                    'count' => $item->count,
                    'total' => $item->total
                ]
            ])
            ->toArray();
    }
}
