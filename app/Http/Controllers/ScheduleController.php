<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\BookingController;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        BookingController::releaseExpiredBookings();
        $query = Schedule::with(['vehicle', 'driver', 'seats']);

        if ($request->has('origin')) {
            $query->where('origin', 'like', "%{$request->get('origin')}%");
        }

        if ($request->has('destination')) {
            $query->where('destination', 'like', "%{$request->get('destination')}%");
        }

        if ($request->has('date')) {
            // Jika filter tanggal spesifik, hanya tampilkan tanggal itu
            $query->whereDate('departure_time', $request->get('date'));
        } else {
            // Default: hanya tampilkan jadwal yang BELUM lewat (mulai dari sekarang)
            $query->where('departure_time', '>=', now());
        }

        // Urutkan dari yang paling dekat
        $query->orderBy('departure_time', 'asc');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:users,id',
            'origin' => 'required|string',
            'destination' => 'required|string',
            'departure_time' => 'required|date',
        ]);

        return DB::transaction(function () use ($request) {
            $schedule = Schedule::create($request->all());

            // Create seats based on vehicle capacity
            $vehicle = Vehicle::find($request->vehicle_id);
            for ($i = 1; $i <= $vehicle->capacity; $i++) {
                Seat::create([
                    'schedule_id' => $schedule->id,
                    'seat_number' => (string)$i,
                    'status' => 'available',
                ]);
            }

            // Create initial trip record
            Trip::create([
                'schedule_id' => $schedule->id,
                'status' => 'scheduled',
            ]);

            return response()->json($schedule->load('seats'), 201);
        });
    }

    public function show(Schedule $schedule)
    {
        BookingController::releaseExpiredBookings();
        return response()->json($schedule->load(['vehicle', 'driver', 'seats', 'trip']));
    }

    public function seats(Schedule $schedule)
    {
        return response()->json($schedule->seats);
    }
}
