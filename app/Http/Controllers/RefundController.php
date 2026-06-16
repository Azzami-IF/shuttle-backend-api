<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    /**
     * Request refund
     */
    public function requestRefund(Request $request, $bookingId)
    {
        try {
            $booking = Booking::with('payment')->findOrFail($bookingId);
            
            // Only booking owner or admin can request refund
            $user = $request->user();
            if ($booking->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Only booked bookings can be refunded
            if ($booking->status !== 'booked') {
                return response()->json(['message' => 'Booking cannot be refunded (current status: ' . $booking->status . ')'], 400);
            }

            // Find associated payment
            $payment = $booking->payment;
            if (!$payment) {
                return response()->json(['message' => 'No payment found for this booking'], 400);
            }

            // Process refund
            $refundResult = PaymentService::refundPayment($payment->stripe_payment_intent_id);
            
            if ($refundResult) {
                // Update booking status
                $booking->update(['status' => 'cancelled']);
                
                Log::info('Refund processed for booking: ' . $bookingId);

                return response()->json([
                    'message' => 'Refund processed successfully',
                    'booking' => $booking,
                    'payment_status' => $payment->status,
                ]);
            }
            
            return response()->json(['message' => 'Refund processing failed'], 400);
        } catch (\Exception $e) {
            Log::error('Request refund failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get refund status
     */
    public function getRefundStatus(Request $request, $bookingId)
    {
        try {
            $booking = Booking::with('payment')->findOrFail($bookingId);
            
            // Check authorization
            $user = $request->user();
            if ($booking->user_id !== $user->id && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            return response()->json([
                'booking_id' => $booking->id,
                'status' => $booking->status,
                'payment_status' => $booking->payment?->status,
                'paid_at' => $booking->payment?->paid_at,
                'refunded_at' => $booking->payment?->refunded_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Get refund status failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
