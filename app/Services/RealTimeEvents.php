<?php

namespace App\Services;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time Events for WebSocket Broadcasting
 * 
 * Events that are broadcast to connected clients:
 * - Booking updates
 * - Driver location
 * - Vehicle status
 * - Payment completed
 * - Admin metrics
 * - User notifications
 */

// ===========================
// BOOKING EVENTS
// ===========================

class BookingUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $bookingId;
    public string $status;
    public array $data;
    public string $timestamp;

    public function __construct(int $bookingId, string $status, array $data = [])
    {
        $this->bookingId = $bookingId;
        $this->status = $status;
        $this->data = $data;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('bookings:updates'),
            new PrivateChannel('user.' . ($this->data['user_id'] ?? 0) . '.bookings'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.updated';
    }
}

// ===========================
// DRIVER EVENTS
// ===========================

class DriverLocationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driverId;
    public float $latitude;
    public float $longitude;
    public ?string $heading;
    public string $timestamp;

    public function __construct(int $driverId, float $latitude, float $longitude, ?string $heading = null)
    {
        $this->driverId = $driverId;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->heading = $heading;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('drivers:location'),
            new PrivateChannel('driver.' . $this->driverId . '.location'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver.location.updated';
    }
}

// ===========================
// VEHICLE EVENTS
// ===========================

class VehicleStatusEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $vehicleId;
    public string $status;
    public array $data;
    public string $timestamp;

    public function __construct(int $vehicleId, string $status, array $data = [])
    {
        $this->vehicleId = $vehicleId;
        $this->status = $status;
        $this->data = $data;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('vehicles:status'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.status.updated';
    }
}

// ===========================
// PAYMENT EVENTS
// ===========================

class PaymentCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $paymentId;
    public int $bookingId;
    public float $amount;
    public string $timestamp;

    public function __construct(int $paymentId, int $bookingId, float $amount)
    {
        $this->paymentId = $paymentId;
        $this->bookingId = $bookingId;
        $this->amount = $amount;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.completed';
    }
}

// ===========================
// ADMIN EVENTS
// ===========================

class AdminMetricsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $metrics;
    public string $timestamp;

    public function __construct(array $metrics)
    {
        $this->metrics = $metrics;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('admin:dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.metrics.updated';
    }
}

class AdminDashboardEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $metrics;
    public array $activeBookings;
    public int $activeDrivers;
    public array $revenueData;
    public string $timestamp;

    public function __construct(array $metrics)
    {
        $this->metrics = $metrics;
        $this->activeBookings = $metrics['active_bookings'] ?? [];
        $this->activeDrivers = $metrics['active_drivers'] ?? 0;
        $this->revenueData = $metrics['revenue'] ?? [];
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('admin:dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.dashboard.updated';
    }
}

// ===========================
// USER NOTIFICATION EVENTS
// ===========================

class UserNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $type;
    public string $message;
    public array $data;
    public string $timestamp;

    public function __construct(int $userId, string $type, string $message, array $data = [])
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->message = $message;
        $this->data = $data;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId . '.notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.notification';
    }
}

// ===========================
// TRIP EVENTS
// ===========================

class TripStartedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tripId;
    public int $driverId;
    public int $userId;
    public array $tripData;
    public string $timestamp;

    public function __construct(int $tripId, int $driverId, int $userId, array $tripData = [])
    {
        $this->tripId = $tripId;
        $this->driverId = $driverId;
        $this->userId = $userId;
        $this->tripData = $tripData;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('trip.' . $this->tripId . '.active'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.started';
    }
}
