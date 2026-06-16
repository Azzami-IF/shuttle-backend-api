<?php

namespace App\Listeners;

use App\Events\TripStarted;
use App\Jobs\SendEmailNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class NotifyPassengersOfTripStart
{
    public function handle(TripStarted $event): void
    {
        try {
            $trip = $event->trip;
            $schedule = $trip->schedule;
            $bookings = $schedule->bookings()->where('status', 'booked')->get();

            foreach ($bookings as $booking) {
                Queue::push(new SendEmailNotification(
                    $booking->user_id,
                    'Your Trip is Starting - Driver on the Way',
                    'emails.trip-started',
                    [
                        'type' => 'trip_started',
                        'booking' => $booking,
                        'schedule' => $schedule,
                        'trip' => $trip,
                        'message' => 'Your trip is now starting. The driver is on the way to pick you up.',
                    ]
                ));
            }

            Log::info('Trip start notifications queued for trip ' . $trip->id);
        } catch (\Exception $e) {
            Log::error('Trip start notification listener failed: ' . $e->getMessage());
        }
    }
}
