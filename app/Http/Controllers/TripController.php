<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    private array $driverStatuses = ['scheduled', 'boarding', 'on-going', 'arrived', 'delayed', 'completed'];
    private array $persistedStatuses = ['scheduled', 'on-going', 'completed'];
    private array $statusMap = [
        'boarding' => 'on-going',
        'arrived' => 'on-going',
        'delayed' => 'on-going',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'driver') {
            return response()->json(Trip::whereHas('schedule', function ($query) use ($user) {
                $query->where('driver_id', $user->id);
            })->with(['schedule.vehicle', 'schedule.driver'])->get());
        }
        return response()->json(Trip::with(['schedule.vehicle', 'schedule.driver'])->get());
    }

    public function start(Request $request, Trip $trip)
    {
        $user = $request->user();
        if ($user->role !== 'driver' || $trip->schedule->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Allow starting if trip is scheduled or on-going (in case status was already updated)
        if (!in_array($trip->status, ['scheduled', 'on-going'])) {
            return response()->json(['message' => 'Trip already started or completed'], 422);
        }

        $trip->update([
            'status' => 'on-going',
            'started_at' => now(),
        ]);

        // Dispatch event to notify passengers
        event(new \App\Events\TripStarted($trip->load(['schedule.vehicle', 'schedule.driver'])));

        return response()->json($trip);
    }

    public function complete(Request $request, Trip $trip)
    {
        $user = $request->user();
        if ($user->role !== 'driver' || $trip->schedule->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($trip->status !== 'on-going') {
            return response()->json(['message' => 'Trip not in progress'], 422);
        }

        return DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update all bookings for this schedule to completed
            $trip->schedule->bookings()->where('status', 'booked')->update(['status' => 'completed']);

            // Dispatch event to notify passengers
            event(new \App\Events\TripCompleted($trip->load(['schedule.vehicle', 'schedule.driver'])));

            return response()->json($trip);
        });
    }

    public function updateStatus(Request $request, Trip $trip)
    {
        $user = $request->user();
        if ($user->role !== 'driver' || $trip->schedule->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:boarding,on-going,arrived,delayed,completed',
        ]);

        $status = $request->string('status')->toString();

        if (!in_array($status, $this->driverStatuses, true)) {
            return response()->json(['message' => 'Unsupported status'], 422);
        }

        if ($status === 'completed') {
            if ($trip->status !== 'on-going' && $trip->status !== 'boarding' && $trip->status !== 'arrived') {
                return response()->json(['message' => 'Trip not in progress'], 422);
            }

            return $this->complete($request, $trip);
        }

        if ($trip->status === 'completed') {
            return response()->json(['message' => 'Trip already completed'], 422);
        }

        $persistedStatus = $this->statusMap[$status] ?? $status;

        if (!in_array($persistedStatus, $this->persistedStatuses, true)) {
            return response()->json(['message' => 'Unsupported status transition'], 422);
        }

        $trip->update(['status' => $persistedStatus]);

        event(new \App\Events\TripStarted($trip->load(['schedule.vehicle', 'schedule.driver'])));

        return response()->json($trip->load(['schedule.vehicle', 'schedule.driver']));
    }

    public function show(Request $request, Trip $trip)
    {
        $user = $request->user();
        
        // Admins can view any trip
        if ($user->role === 'admin') {
            return response()->json($trip->load(['schedule.vehicle', 'schedule.driver', 'locations']));
        }
        
        // Drivers can view their own trips
        if ($user->role === 'driver') {
            if ($trip->schedule->driver_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return response()->json($trip->load(['schedule.vehicle', 'schedule.driver', 'locations']));
        }
        
        // Customers can only view trips they have bookings for
        if ($user->role === 'customer') {
            $hasBooking = $trip->schedule->bookings()->where('user_id', $user->id)->exists();
            if (!$hasBooking) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        
        return response()->json($trip->load(['schedule.vehicle', 'schedule.driver', 'locations']));
    }
}
