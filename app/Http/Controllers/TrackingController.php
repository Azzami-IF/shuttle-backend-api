<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Trip;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function update(Request $request, Trip $trip)
    {
        $user = $request->user();
        if ($user->role !== 'driver' || $trip->schedule->driver_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($trip->status === 'scheduled' || $trip->status === 'completed') {
            return response()->json(['message' => 'Trip not active'], 422);
        }

        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $location = Location::create([
            'trip_id' => $trip->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // Broadcast real-time location update to passengers and admins
        $schedule = $trip->schedule;
        $vehicleInfo = [];
        if ($schedule) {
            $vehicleInfo = [
                'plate_number' => $schedule->vehicle->license_plate ?? 'Unknown',
                'driver_name' => $schedule->driver->name ?? 'Unknown',
            ];
        }

        broadcast(new \App\Events\DriverLocationUpdated(
            (int) $schedule->id,
            (float) $request->latitude,
            (float) $request->longitude,
            $location->created_at->toIso8601String(),
            $vehicleInfo
        ))->toOthers();

        return response()->json($location, 201);
    }

    public function latest(Request $request, Trip $trip)
    {
        $user = $request->user();
        
        // Admins can view any trip location
        if ($user->role === 'admin') {
            $location = $trip->locations()->latest()->first();
            return response()->json($location);
        }
        
        // Drivers can view their own trip locations
        if ($user->role === 'driver') {
            if ($trip->schedule->driver_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $location = $trip->locations()->latest()->first();
            return response()->json($location);
        }
        
        // Customers can only view locations for trips they booked
        if ($user->role === 'customer') {
            $hasBooking = $trip->schedule->bookings()->where('user_id', $user->id)->exists();
            if (!$hasBooking) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        
        $location = $trip->locations()->latest()->first();
        return response()->json($location);
    }

    public function history(Request $request, Trip $trip)
    {
        $user = $request->user();
        
        // Admins can view any trip location history
        if ($user->role === 'admin') {
            return response()->json($trip->locations()->orderBy('created_at', 'asc')->get());
        }
        
        // Drivers can view their own trip location history
        if ($user->role === 'driver') {
            if ($trip->schedule->driver_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return response()->json($trip->locations()->orderBy('created_at', 'asc')->get());
        }
        
        // Customers can only view location history for trips they booked
        if ($user->role === 'customer') {
            $hasBooking = $trip->schedule->bookings()->where('user_id', $user->id)->exists();
            if (!$hasBooking) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        
        return response()->json($trip->locations()->orderBy('created_at', 'asc')->get());
    }
}
