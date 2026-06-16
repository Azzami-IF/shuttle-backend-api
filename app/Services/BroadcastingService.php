<?php

namespace App\Services;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\InteractsWithBroadcasting;

/**
 * Broadcasting Service
 * 
 * Manages real-time event broadcasting:
 * - Channel management
 * - Event publishing
 * - Presence tracking
 * - Connection pooling
 */
class BroadcastingService
{
    use InteractsWithBroadcasting;

    /**
     * Channel definitions
     */
    private const CHANNELS = [
        // Public channels (anyone can listen)
        'bookings:updates' => 'public',
        'drivers:location' => 'public',
        'vehicles:status' => 'public',
        'admin:metrics' => 'public',

        // Private channels (authenticated users)
        'user.{id}.bookings' => 'private',
        'user.{id}.notifications' => 'private',
        'driver.{id}.location' => 'private',
        'driver.{id}.bookings' => 'private',

        // Presence channels (track who's online)
        'admin:dashboard' => 'presence',
        'booking.{id}.passengers' => 'presence',
        'trip.{id}.active' => 'presence',
    ];

    private array $broadcastMetrics = [
        'events_published' => 0,
        'active_connections' => 0,
        'messages_sent' => 0,
        'bytes_transmitted' => 0,
    ];

    /**
     * Register channel authorization rules
     */
    public static function registerChannels(): void
    {
        // Private channel: user's own booking updates
        Broadcast::channel('user.{id}.bookings', function ($user, $id) {
            return (int) $user->id === (int) $id;
        });

        // Private channel: user's notifications
        Broadcast::channel('user.{id}.notifications', function ($user, $id) {
            return (int) $user->id === (int) $id;
        });

        // Private channel: driver's location/bookings
        Broadcast::channel('driver.{id}.location', function ($user, $id) {
            return $user->role === 'driver' && (int) $user->id === (int) $id;
        });

        // Presence channel: admin dashboard
        Broadcast::channel('admin:dashboard', function ($user) {
            return $user->role === 'admin' || $user->role === 'superadmin';
        });

        // Presence channel: active booking passengers
        Broadcast::channel('booking.{id}.passengers', function ($user, $id) {
            $booking = \App\Models\Booking::find($id);
            return $booking && (int) $user->id === (int) $booking->user_id;
        });

        // Presence channel: active trip
        Broadcast::channel('trip.{id}.active', function ($user, $id) {
            $trip = \App\Models\Trip::find($id);
            return $trip && (
                (int) $user->id === (int) $trip->driver_id ||
                (int) $user->id === (int) $trip->user_id
            );
        });
    }

    /**
     * Broadcast booking update event
     */
    public function broadcastBookingUpdate(int $bookingId, string $status, array $data = []): bool
    {
        try {
            broadcast(new \App\Events\BookingUpdated($bookingId, $status, $data))->toOthers();
            $this->recordMetric('events_published');
            Log::debug("Booking update broadcast", ['booking_id' => $bookingId, 'status' => $status]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast booking update", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast driver location update
     */
    public function broadcastDriverLocation(int $driverId, float $latitude, float $longitude, string $heading = null): bool
    {
        try {
            broadcast(new \App\Events\DriverLocationUpdated($driverId, $latitude, $longitude, $heading))->toOthers();
            $this->recordMetric('events_published');
            $this->recordMetric('bytes_transmitted', 256); // ~256 bytes per location update
            Log::debug("Driver location broadcast", ['driver_id' => $driverId]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast driver location", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast vehicle status update
     */
    public function broadcastVehicleStatus(int $vehicleId, string $status, array $data = []): bool
    {
        try {
            broadcast(new \App\Events\VehicleStatusUpdated($vehicleId, $status, $data))->toOthers();
            $this->recordMetric('events_published');
            Log::debug("Vehicle status broadcast", ['vehicle_id' => $vehicleId, 'status' => $status]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast vehicle status", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast payment event
     */
    public function broadcastPaymentCompleted(int $paymentId, int $bookingId, float $amount): bool
    {
        try {
            broadcast(new \App\Events\PaymentCompleted($paymentId, $bookingId, $amount))->toOthers();
            $this->recordMetric('events_published');
            Log::debug("Payment completed broadcast", ['payment_id' => $paymentId]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast payment", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast admin metrics update
     */
    public function broadcastAdminMetrics(array $metrics): bool
    {
        try {
            broadcast(new \App\Events\AdminMetricsUpdated($metrics))->toOthers();
            $this->recordMetric('events_published');
            Log::debug("Admin metrics broadcast");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast admin metrics", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast notification event
     */
    public function broadcastNotification(int $userId, string $type, string $message, array $data = []): bool
    {
        try {
            broadcast(new \App\Events\UserNotification($userId, $type, $message, $data))
                ->toOthers();
            $this->recordMetric('events_published');
            Log::debug("Notification broadcast", ['user_id' => $userId, 'type' => $type]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast notification", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast real-time admin dashboard update
     */
    public function broadcastAdminDashboardUpdate(array $metrics): bool
    {
        try {
            broadcast(new \App\Events\AdminDashboardUpdate($metrics))->toOthers();
            $this->recordMetric('events_published');
            $this->recordMetric('bytes_transmitted', 1024); // Dashboard metrics ~1KB
            Log::debug("Admin dashboard update broadcast");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast admin dashboard update", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get presence on a channel
     */
    public function getPresence(string $channel): array
    {
        try {
            return \Illuminate\Support\Facades\Redis::lrange("presence:$channel", 0, -1);
        } catch (\Exception $e) {
            Log::error("Failed to get presence", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get channel statistics
     */
    public function getChannelStats(string $channel = null): array
    {
        try {
            if ($channel) {
                return [
                    'channel' => $channel,
                    'subscribers' => \Illuminate\Support\Facades\Redis::get("subscribers:$channel"),
                    'presence' => $this->getPresence($channel),
                ];
            }

            // Get stats for all channels
            $stats = [];
            foreach (array_keys(self::CHANNELS) as $ch) {
                $stats[$ch] = [
                    'subscribers' => \Illuminate\Support\Facades\Redis::get("subscribers:$ch") ?? 0,
                    'presence_count' => count($this->getPresence($ch)),
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error("Failed to get channel stats", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Record broadcast metric
     */
    private function recordMetric(string $metric, int $value = 1): void
    {
        if (isset($this->broadcastMetrics[$metric])) {
            $this->broadcastMetrics[$metric] += $value;
        }
    }

    /**
     * Get broadcasting metrics
     */
    public function getMetrics(): array
    {
        return array_merge($this->broadcastMetrics, [
            'uptime' => time() - (\Illuminate\Support\Facades\Redis::get('broadcast_start_time') ?? time()),
            'avg_latency_ms' => \Illuminate\Support\Facades\Redis::get('avg_broadcast_latency') ?? 0,
        ]);
    }

    /**
     * Reset metrics
     */
    public function resetMetrics(): void
    {
        $this->broadcastMetrics = [
            'events_published' => 0,
            'active_connections' => 0,
            'messages_sent' => 0,
            'bytes_transmitted' => 0,
        ];
    }
}
