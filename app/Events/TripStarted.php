<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripStarted
{
    use Dispatchable, SerializesModels, InteractsWithBroadcasting;

    public function __construct(public Trip $trip)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('trip.' . $this->trip->id),
        ];
    }
}
