<?php
namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Exception;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('stripe.secret_key'));
    }

    /**
     * Create payment intent for booking
     */
    public static function createPaymentIntent($bookingId, $amount, $currency = 'usd')
    {
        try {
            Stripe::setApiKey(config('stripe.secret_key'));
            
            $intent = PaymentIntent::create([
                'amount' => (int)($amount * 100), // Convert to cents
                'currency' => $currency,
                'metadata' => [
                    'booking_id' => $bookingId,
                ]
            ]);

            Payment::create([
                'booking_id' => $bookingId,
                'stripe_payment_intent_id' => $intent->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            return [
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
            ];
        } catch (Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());
            throw new Exception('Payment intent creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Confirm payment
     */
    public static function confirmPayment($paymentIntentId)
    {
        try {
            Stripe::setApiKey(config('stripe.secret_key'));
            
            $intent = PaymentIntent::retrieve($paymentIntentId);
            
            if ($intent->status === 'succeeded') {
                Payment::where('stripe_payment_intent_id', $paymentIntentId)
                    ->update(['status' => 'completed', 'paid_at' => now()]);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            Log::error('Payment confirmation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund payment
     */
    public static function refundPayment($paymentIntentId, $amount = null)
    {
        try {
            Stripe::setApiKey(config('stripe.secret_key'));
            
            $intent = PaymentIntent::retrieve($paymentIntentId);
            
            // Find the charge
            if ($intent->charges->data) {
                $charge = $intent->charges->data[0];
                
                $refund = $charge->refund([
                    'amount' => $amount ? (int)($amount * 100) : null,
                ]);
                
                $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
                if ($payment) {
                    $payment->update([
                        'status' => 'refunded',
                        'refunded_at' => now(),
                    ]);
                }
                
                Log::info('Refund processed for payment: ' . $paymentIntentId);
                return $refund;
            }
            
            return false;
        } catch (Exception $e) {
            Log::error('Refund failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get payment status
     */
    public static function getPaymentStatus($paymentIntentId)
    {
        try {
            Stripe::setApiKey(config('stripe.secret_key'));
            
            $intent = PaymentIntent::retrieve($paymentIntentId);
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            
            return [
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
                'db_status' => $payment?->status,
                'paid_at' => $payment?->paid_at,
                'refunded_at' => $payment?->refunded_at,
            ];
        } catch (Exception $e) {
            Log::error('Get payment status failed: ' . $e->getMessage());
            return null;
        }
    }
}
