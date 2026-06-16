<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Jobs\SendEmailNotification;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationEmail
{
    public function handle(PaymentConfirmed $event): void
    {
        try {
            $booking = $event->booking;
            
            // Generate invoice
            try {
                InvoiceService::generateInvoice($booking->id);
            } catch (\Exception $e) {
                Log::warning('Invoice generation failed: ' . $e->getMessage());
            }

            // Send confirmation email
            Queue::push(new SendEmailNotification(
                $booking->user_id,
                'Booking Confirmation - Payment Received',
                'emails.booking-confirmation',
                [
                    'type' => 'payment_confirmed',
                    'booking' => $booking,
                    'schedule' => $booking->schedule,
                    'message' => 'Your payment has been confirmed. Your booking is now active.',
                ]
            ));

            Log::info('Payment confirmation email queued for booking ' . $booking->id);
        } catch (\Exception $e) {
            Log::error('Payment confirmation listener failed: ' . $e->getMessage());
        }
    }
}
