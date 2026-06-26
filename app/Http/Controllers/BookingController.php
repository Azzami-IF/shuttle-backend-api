<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Seat;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        self::releaseExpiredBookings();
        $user = $request->user();
        $query = Booking::with(['user', 'schedule.trip', 'schedule.vehicle', 'schedule.driver', 'seat']);

        if ($request->has('payment_code')) {
            $query->where('payment_code', $request->get('payment_code'));
        } elseif ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->whereHas('schedule', function ($q) use ($search) {
                $q->where('origin', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        if ($request->has('schedule_id')) {
            $query->where('schedule_id', $request->get('schedule_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'seat_ids' => 'required|array',
            'seat_ids.*' => 'exists:seats,id',
        ]);

        return DB::transaction(function () use ($request) {
            $bookings = [];
            $schedule = Schedule::with('trip')->findOrFail($request->schedule_id);

            // Exclude past departure times
            if (\Carbon\Carbon::parse($schedule->departure_time)->isPast()) {
                throw ValidationException::withMessages(['schedule_id' => 'Cannot book seats for a past departure schedule']);
            }

            // Exclude active or completed trips
            if ($schedule->trip && $schedule->trip->status !== 'scheduled') {
                throw ValidationException::withMessages(['schedule_id' => 'Cannot book seats for a trip that has already departed or completed']);
            }

            $paymentCode = 'TRF' . strtoupper(bin2hex(random_bytes(4)));
            $uniqueCode = rand(100, 999);

            foreach ($request->seat_ids as $seatId) {
                $seat = Seat::lockForUpdate()->find($seatId);

                if ($seat->schedule_id != $request->schedule_id) {
                    throw ValidationException::withMessages(['seat_ids' => "Seat #$seatId does not belong to this schedule"]);
                }

                if ($seat->status !== 'available') {
                    throw ValidationException::withMessages(['seat_ids' => "Seat #$seatId already booked"]);
                }

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'schedule_id' => $request->schedule_id,
                    'seat_id' => $seatId,
                    'status' => 'pending_payment',
                    'payment_code' => $paymentCode,
                    'unique_code' => $uniqueCode,
                ]);

                $seat->update(['status' => 'booked']);
                $bookings[] = $booking;
            }

            // Invalidate related caches
            \App\Services\CacheManager::invalidateBookingCache($request->schedule_id);

            // Calculate total price for all bookings with this payment code
            $totalPrice = collect($bookings)->sum(function($b) {
                return $b->schedule->price;
            });

            // For the response, we return the first booking but with the total price of the group
            $responseBooking = $bookings[0]->load(['schedule', 'seat', 'user']);
            $responseBooking->total_price = $totalPrice;
            $responseBooking->seat_ids = $request->seat_ids; // Inform front-end about other seats

            return response()->json($responseBooking, 201);
        });
    }

    public function confirmPayment(Request $request, Booking $booking)
    {
        if ($request->user()->id !== $booking->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // if ($booking->status !== 'pending_payment') {
        //     return response()->json(['message' => 'Booking is not in pending payment status'], 422);
        // }

        $booking->update(['status' => 'pending_verification']);
        
        // Dispatch event to trigger notifications and invoice generation
        event(new \App\Events\PaymentConfirmed($booking->load(['schedule', 'seat', 'user'])));

        return response()->json(['message' => 'Payment confirmed', 'booking' => $booking->load(['schedule', 'seat'])]);
    }

    public function show(Request $request, Booking $booking)
    {
        if ($request->user()->id !== $booking->user_id && $request->user()->role !== 'admin') {
            $paymentCode = $request->query('payment_code');
            if (!$paymentCode || $paymentCode !== $booking->payment_code) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        return response()->json($booking->load(['user', 'schedule', 'seat']));
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($request->user()->id !== $booking->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'booked' && $booking->status !== 'pending_payment') {
            return response()->json(['message' => 'Cannot cancel booking in current status'], 422);
        }

        return DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->seat->update(['status' => 'available']);

            // Invalidate related caches
            \App\Services\CacheManager::invalidateBookingCache($booking->schedule_id);

            return response()->json(['message' => 'Booking cancelled successfully']);
        });
    }

    public static function releaseExpiredBookings()
    {
        $expiredBookings = Booking::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'cancelled']);
                if ($booking->seat) {
                    $booking->seat->update(['status' => 'available']);
                }
            });
        }
    }

    public function uploadProof(Request $request, Booking $booking)
    {
        if ($request->user()->id !== $booking->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // if ($booking->status !== 'pending_payment') {
        //     return response()->json(['message' => 'Booking status must be pending payment'], 422);
        // }

        $request->validate([
            'image' => 'required|image|max:10240' // max 10MB
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('proofs', 'public');
            
            // Update ALL bookings with the same payment_code
            $relatedBookings = Booking::where('payment_code', $booking->payment_code)->get();
            
            foreach ($relatedBookings as $b) {
                // Delete old proof if exists (only once per unique path if possible, but simple is fine)
                if ($b->payment_proof && $b->payment_proof !== $path) {
                    // We only delete if no other booking in this group is using it? 
                    // Actually, simple update is safer for now.
                }
                $b->update(['payment_proof' => $path]);
            }

            return response()->json([
                'message' => 'Payment proof uploaded successfully for all related seats',
                'payment_proof' => $path
            ]);
        }

        return response()->json(['message' => 'No image provided'], 400);
    }
}
