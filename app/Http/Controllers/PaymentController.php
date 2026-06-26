<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Create payment intent
     */
    public function createPaymentIntent(Request $request, $bookingId)
    {
        try {
            $booking = Booking::with('schedule')->findOrFail($bookingId);
            
            // Check if user is owner
            if ($booking->user_id != $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Check if already paid
            if ($booking->status === 'booked') {
                return response()->json(['message' => 'Booking already paid'], 400);
            }

            $amount = $booking->schedule->price ?? 50000;
            
            $paymentIntent = PaymentService::createPaymentIntent(
                $bookingId,
                $amount
            );

            return response()->json($paymentIntent);
        } catch (\Exception $e) {
            Log::error('Create payment intent failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Confirm payment
     */
    public function confirmPayment(Request $request, $bookingId)
    {
        try {
            $request->validate([
                'payment_intent_id' => 'required|string',
            ]);

            $booking = Booking::findOrFail($bookingId);
            
            if ($booking->user_id != $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $success = PaymentService::confirmPayment($request->payment_intent_id);
            
            if ($success) {
                $booking->update(['status' => 'booked']);
                
                // Generate invoice
                try {
                    InvoiceService::generateInvoice($bookingId);
                } catch (\Exception $e) {
                    Log::warning('Invoice generation skipped: ' . $e->getMessage());
                }
                
                return response()->json([
                    'message' => 'Payment confirmed',
                    'booking' => $booking
                ]);
            }
            
            return response()->json(['message' => 'Payment not completed'], 400);
        } catch (\Exception $e) {
            Log::error('Confirm payment failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, $bookingId)
    {
        try {
            $booking = Booking::with('payment')->findOrFail($bookingId);
            
            if ($booking->user_id != $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $payment = $booking->payment;
            
            if (!$payment) {
                return response()->json(['message' => 'No payment found'], 404);
            }

            $stripeStatus = PaymentService::getPaymentStatus($payment->stripe_payment_intent_id);

            return response()->json([
                'booking_id' => $booking->id,
                'booking_status' => $booking->status,
                'payment' => $stripeStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Get payment status failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Webhook to handle Stripe events
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage());
            return response('Webhook error', 400);
        }

        // Handle specific events
        match($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSuccess($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            'charge.refunded' => $this->handleRefund($event),
        };

        return response('Webhook received', 200);
    }

    private function handlePaymentSuccess($event)
    {
        try {
            $intent = $event->data->object;
            $bookingId = $intent->metadata->booking_id ?? null;
            
            if ($bookingId) {
                $booking = Booking::find($bookingId);
                if ($booking && $booking->status !== 'booked') {
                    $booking->update(['status' => 'booked']);
                    Log::info('Payment success webhook processed for booking: ' . $bookingId);
                }
            }
        } catch (\Exception $e) {
            Log::error('Handle payment success failed: ' . $e->getMessage());
        }
    }

    private function handlePaymentFailed($event)
    {
        try {
            $intent = $event->data->object;
            Log::warning('Payment failed webhook: ' . json_encode($intent));
        } catch (\Exception $e) {
            Log::error('Handle payment failed error: ' . $e->getMessage());
        }
    }

    private function handleRefund($event)
    {
        try {
            $refund = $event->data->object;
            Log::info('Refund processed webhook: ' . json_encode($refund));
        } catch (\Exception $e) {
            Log::error('Handle refund error: ' . $e->getMessage());
        }
    }
}
