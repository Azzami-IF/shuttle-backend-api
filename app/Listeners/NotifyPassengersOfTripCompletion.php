<?php

namespace App\Listeners;

use App\Events\TripCompleted;
use App\Jobs\SendEmailNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class NotifyPassengersOfTripCompletion
{
    public function handle(TripCompleted $event): void
    {
        try {
            $trip = $event->trip;
            $schedule = $trip->schedule;
            $bookings = $schedule->bookings()->where('status', 'completed')->get();

            foreach ($bookings as $booking) {
                Queue::push(new SendEmailNotification(
                    $booking->user_id,
                    'Your Trip is Complete - Thank You!',
                    'emails.trip-completed',
                    [
                        'type' => 'trip_completed',
                        'booking' => $booking,
                        'schedule' => $schedule,
                        'trip' => $trip,
                        'message' => 'Your trip has been completed successfully.',
                    ]
                ));
            }

            Log::info('Trip completion notifications queued for trip ' . $trip->id);
        } catch (\Exception $e) {
            Log::error('Trip completion notification listener failed: ' . $e->getMessage());
        }
    }
}
