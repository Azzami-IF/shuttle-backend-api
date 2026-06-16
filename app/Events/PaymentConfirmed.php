<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels, InteractsWithBroadcasting;

    public function __construct(public Booking $booking)
    {
        //
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->booking->user_id),
        ];
    }
}
