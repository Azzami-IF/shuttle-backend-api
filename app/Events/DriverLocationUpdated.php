<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $scheduleId,
        public float $latitude,
        public float $longitude,
        public string $recordedAt,
        public array $vehicleInfo = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("schedules.{$this->scheduleId}"),
            new PrivateChannel("admin.tracking")
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'recorded_at' => $this->recordedAt,
            'vehicle' => $this->vehicleInfo
        ];
    }
}
