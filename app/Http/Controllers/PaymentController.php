<?php
namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use App\Services\DompetXService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

    /**
     * Create checkout intent with DompetX
     */
    public function createDompetXCheckout(Request $request, $bookingId)
    {
        try {
            $booking = Booking::with('schedule')->findOrFail($bookingId);
            
            if ($booking->user_id != $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($booking->status === 'booked') {
                return response()->json(['message' => 'Booking already paid'], 400);
            }

            $amount = $booking->schedule->price ?? 50000;
            
            // Check if there are other bookings with same payment_code (bulk seats checkout)
            $totalAmount = Booking::where('payment_code', $booking->payment_code)
                ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
                ->sum('schedules.price');
                
            $amountToPay = $totalAmount > 0 ? $totalAmount : $amount;

            $checkout = DompetXService::createCheckout(
                $bookingId,
                $amountToPay,
                $request->user()->email
            );

            // Save payment entry if not exists
            Payment::updateOrCreate(
                ['booking_id' => $bookingId],
                [
                    'stripe_payment_intent_id' => $checkout['reference'] ?? $checkout['id'] ?? 'dompetx-' . uniqid(),
                    'amount' => $amountToPay,
                    'currency' => 'IDR',
                    'status' => 'pending',
                ]
            );

            return response()->json($checkout);
        } catch (\Exception $e) {
            Log::error('Create DompetX checkout failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Webhook to handle DompetX events
     */
    public function dompetxWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('X-DOMPAY-Signature');
        $timestamp = $request->header('X-DOMPAY-Timestamp');
        $secret = config('dompetx.api_secret') ?? 'secret';

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        if ($sigHeader !== $expectedSignature) {
            Log::warning('DompetX Webhook signature verification failed');
            return response('Invalid signature', 400);
        }

        try {
            $data = json_decode($payload, true);
            $status = $data['status'] ?? '';
            $bookingId = $data['metadata']['booking_id'] ?? null;

            if (($status === 'completed' || $status === 'success') && $bookingId) {
                $booking = Booking::find($bookingId);
                if ($booking && $booking->status !== 'booked') {
                    // Update all seats associated with same payment_code
                    DB::transaction(function () use ($booking, $bookingId) {
                        $paymentCode = $booking->payment_code;
                        
                        Booking::where('payment_code', $paymentCode)->update(['status' => 'booked']);
                        
                        Payment::where('booking_id', $bookingId)->update([
                            'status' => 'completed',
                            'paid_at' => now()
                        ]);
                        
                        // Generate invoice for each booking seat
                        $bookingsToInvoice = Booking::where('payment_code', $paymentCode)->get();
                        foreach ($bookingsToInvoice as $b) {
                            try {
                                InvoiceService::generateInvoice($b->id);
                            } catch (\Exception $e) {
                                Log::warning('Invoice generation failed in webhook: ' . $e->getMessage());
                            }
                        }
                    });
                    Log::info('DompetX payment success processed for booking group: ' . $booking->payment_code);
                }
            }
            return response('Webhook received', 200);
        } catch (\Exception $e) {
            Log::error('DompetX webhook error: ' . $e->getMessage());
            return response('Webhook error', 500);
        }
    }

    /**
     * Render Mock Checkout View
     */
    public function mockCheckoutView(Request $request)
    {
        $bookingId = $request->query('booking_id');
        $reference = $request->query('reference');
        $amount = $request->query('amount');
        
        $booking = Booking::with('schedule.vehicle')->find($bookingId);
        $route = $booking ? "{$booking->schedule->origin} ➔ {$booking->schedule->destination}" : "Rute Perjalanan";
        $vehicle = $booking ? ($booking->schedule->vehicle->name ?? 'Shuttle Bus') : "Shuttle Bus";
        $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DompetX Secure Payment Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --card-bg: rgba(22, 30, 49, 0.75);
            --border: rgba(56, 128, 255, 0.15);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.08), transparent 60%);
        }
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 2rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .header {
            margin-bottom: 2rem;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .bill-details {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        .bill-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }
        .bill-row:last-child { margin-bottom: 0; }
        .bill-label { color: var(--text-muted); }
        .bill-val { font-weight: 600; }
        .amount-highlight {
            font-size: 2rem;
            font-weight: 800;
            color: var(--success);
            margin: 1.5rem 0;
            letter-spacing: -0.5px;
        }
        .btn-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
            width: 100%;
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="brand">DompetX</div>
            <div class="subtitle">Secure E-Wallet Checkout</div>
        </div>
        
        <div class="bill-details">
            <div class="bill-row">
                <span class="bill-label">Merchant</span>
                <span class="bill-val">KemanapunGo</span>
            </div>
            <div class="bill-row">
                <span class="bill-label">Reference</span>
                <span class="bill-val">{$reference}</span>
            </div>
            <div class="bill-row">
                <span class="bill-label">Rute</span>
                <span class="bill-val">{$route}</span>
            </div>
            <div class="bill-row">
                <span class="bill-label">Kendaraan</span>
                <span class="bill-val">{$vehicle}</span>
            </div>
        </div>

        <div class="subtitle">TOTAL PEMBAYARAN</div>
        <div class="amount-highlight">{$formattedAmount}</div>

        <form action="/api/dompetx-mock/pay" method="POST" class="btn-stack">
            <input type="hidden" name="booking_id" value="{$bookingId}">
            <input type="hidden" name="reference" value="{$reference}">
            <input type="hidden" name="amount" value="{$amount}">
            
            <button type="submit" name="status" value="success" class="btn btn-success">
                Simulasikan Bayar Sukses
            </button>
            <button type="submit" name="status" value="failed" class="btn btn-danger">
                Batalkan Transaksi
            </button>
        </form>
    </div>
</body>
</html>
HTML;
        return response($html, 200)->header('Content-Type', 'text/html');
    }

    /**
     * Handle Mock Checkout Pay Submission
     */
    public function mockCheckoutPay(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $reference = $request->input('reference');
        $amount = $request->input('amount');
        $status = $request->input('status'); // 'success' or 'failed'

        if ($status === 'success') {
            // 1. Attempt local loopback POST request to webhook
            try {
                $payload = json_encode([
                    'id' => $reference,
                    'reference' => $reference,
                    'status' => 'completed',
                    'amount' => (int) $amount,
                    'currency' => 'IDR',
                    'metadata' => [
                        'booking_id' => (int) $bookingId
                    ]
                ]);
                $timestamp = time();
                $signature = hash_hmac('sha256', $timestamp . '.' . $payload, config('dompetx.api_secret') ?? 'secret');

                Http::withHeaders([
                    'X-DOMPAY-Signature' => $signature,
                    'X-DOMPAY-Timestamp' => $timestamp,
                    'Content-Type' => 'application/json'
                ])->post(url('/api/webhooks/dompetx'), json_decode($payload, true));
            } catch (\Exception $e) {
                Log::warning('Mock Webhook request failed, carrying out DB fallback: ' . $e->getMessage());
            }

            // 2. Local fallback update to guarantee state transition works
            $booking = Booking::find($bookingId);
            if ($booking && $booking->status !== 'booked') {
                DB::transaction(function () use ($booking, $bookingId) {
                    $paymentCode = $booking->payment_code;
                    
                    Booking::where('payment_code', $paymentCode)->update(['status' => 'booked']);
                    
                    Payment::where('booking_id', $bookingId)->update([
                        'status' => 'completed',
                        'paid_at' => now()
                    ]);
                    
                    $bookingsToInvoice = Booking::where('payment_code', $paymentCode)->get();
                    foreach ($bookingsToInvoice as $b) {
                        try {
                            InvoiceService::generateInvoice($b->id);
                        } catch (\Exception $e) {
                            Log::warning('Invoice generation failed in mock pay db fallback: ' . $e->getMessage());
                        }
                    }
                });
            }
        } else {
            // Cancel booking and make seat available if cancelled
            $booking = Booking::find($bookingId);
            if ($booking) {
                DB::transaction(function () use ($booking) {
                    $paymentCode = $booking->payment_code;
                    
                    $bookings = Booking::where('payment_code', $paymentCode)->get();
                    foreach ($bookings as $b) {
                        $b->update(['status' => 'cancelled']);
                        if ($b->seat) {
                            $b->seat->update(['status' => 'available']);
                        }
                    }
                    
                    Payment::where('booking_id', $booking->id)->update([
                        'status' => 'failed'
                    ]);
                });
            }
        }

        // Redirect back to app booking details or checkout
        // Check if origin is ambatu.my.id domain or dev environment
        $appUrl = 'http://kemanapungo.ambatu.my.id';
        if (strpos($request->header('referer') ?? '', 'localhost') !== false) {
            $appUrl = 'http://localhost:8100';
        }
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Pembayaran Selesai</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #090d16;
            color: white;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }
        .status-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        h2 { font-size: 1.75rem; margin-bottom: 0.75rem; }
        p { color: #94a3b8; font-size: 0.95rem; margin-bottom: 2rem; max-width: 320px; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 0.9rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(59,130,246,0.3);
            transition: transform 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="status-icon">
HTML;
        if ($status === 'success') {
            $html .= "✅</div><h2>Pembayaran Berhasil!</h2><p>Transaksi Anda telah diproses. Silakan kembali ke aplikasi KemanapunGo untuk memeriksa tiket perjalanan Anda.</p>";
        } else {
            $html .= "❌</div><h2>Pembayaran Dibatalkan</h2><p>Transaksi Anda telah dibatalkan. Silakan kembali ke aplikasi untuk memilih metode pembayaran lain.</p>";
        }

        // Return the client to the booking detail list or specific booking page
        $redirectUrl = $appUrl . '/#/booking-detail';
        $html .= '<a href="' . $redirectUrl . '" class="btn">Kembali ke Aplikasi</a></body></html>';
        
        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
