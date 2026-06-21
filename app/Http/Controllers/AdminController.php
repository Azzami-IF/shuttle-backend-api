<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Schedule;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'vehicles' => Vehicle::count(),
            'schedules' => Schedule::count(),
            'bookings' => Booking::count(),
            'active_trips' => Trip::whereIn('status', ['boarding', 'on-going', 'delayed', 'arrived'])->count(),
            'drivers' => User::where('role', 'driver')->count(),
        ];

        $recent_bookings = Booking::with(['user', 'schedule'])->latest()->take(5)->get();
        $active_trips = Trip::with(['schedule.vehicle', 'schedule.driver', 'schedule.bookings.user'])->whereIn('status', ['boarding', 'on-going', 'delayed', 'arrived'])->get();

        // Chart Data: Bookings in last 7 days
        $booking_stats = Booking::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $chart_data = [
            'labels' => $booking_stats->pluck('date'),
            'values' => $booking_stats->pluck('total'),
        ];

        return view('admin.dashboard', compact('stats', 'recent_bookings', 'active_trips', 'chart_data'));
    }

    // Vehicle Management
    public function vehicles(Request $request)
    {
        $query = Vehicle::query();
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('license_plate', 'like', "%{$search}%");
        }
        $vehicles = $query->get();
        return view('admin.vehicles.index', compact('vehicles'));
    }

    public function createVehicle()
    {
        return view('admin.vehicles.create');
    }

    public function storeVehicle(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'license_plate' => 'required|unique:vehicles',
            'capacity' => 'required|integer',
        ]);

        Vehicle::create($request->all());
        return redirect()->route('admin.vehicles')->with('success', 'Vehicle created successfully');
    }

    public function editVehicle(Vehicle $vehicle)
    {
        return view('admin.vehicles.edit', compact('vehicle'));
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'name' => 'required',
            'license_plate' => 'required|unique:vehicles,license_plate,' . $vehicle->id,
            'capacity' => 'required|integer',
        ]);

        $vehicle->update($request->all());
        return redirect()->route('admin.vehicles')->with('success', 'Vehicle updated successfully');
    }

    public function deleteVehicle(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('admin.vehicles')->with('success', 'Vehicle deleted successfully');
    }

    // Schedule Management
    public function schedules(Request $request)
    {
        $query = Schedule::with(['vehicle', 'driver']);

        if ($request->has('origin')) {
            $query->where('origin', 'like', "%{$request->get('origin')}%");
        }
        if ($request->has('destination')) {
            $query->where('destination', 'like', "%{$request->get('destination')}%");
        }

        $schedules = $query->get();
        return view('admin.schedules.index', compact('schedules'));
    }

    public function createSchedule()
    {
        $vehicles = Vehicle::all();
        $drivers = User::where('role', 'driver')->get();
        return view('admin.schedules.create', compact('vehicles', 'drivers'));
    }

    public function storeSchedule(Request $request)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:users,id',
            'origin' => 'required',
            'destination' => 'required',
            'departure_time' => 'required|date',
        ]);

        DB::transaction(function () use ($request) {
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
        });

        return redirect()->route('admin.schedules')->with('success', 'Schedule created successfully');
    }

    public function deleteSchedule(Schedule $schedule)
    {
        $schedule->delete();
        return redirect()->route('admin.schedules')->with('success', 'Schedule deleted successfully');
    }

    // User/Driver Management
    public function users(Request $request)
    {
        $query = User::query();
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
        }
        $users = $query->get();
        return view('admin.users.index', compact('users'));
    }

    // User CRUD (web)
    public function createUser()
    {
        return view('admin.users.create');
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'role' => 'required|in:customer,driver,admin',
            'phone' => 'nullable',
            'driver_code' => 'nullable|unique:users,driver_code',
            'password' => 'required|min:8',
        ]);

        $data = $request->only(['name','email','role','phone','driver_code']);
        $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);

        if ($data['role'] === 'driver' && empty($data['driver_code'])) {
            $data['driver_code'] = 'DRV'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));
        }

        \App\Models\User::create($data);
        return redirect()->route('admin.users')->with('success','User created');
    }

    public function editUser(\App\Models\User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(Request $request, \App\Models\User $user)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:customer,driver,admin',
            'phone' => 'nullable',
            'driver_code' => 'nullable|unique:users,driver_code,'.$user->id,
            'password' => 'nullable|min:8',
        ]);

        $data = $request->only(['name','email','role','phone','driver_code']);
        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $user->update($data);
        return redirect()->route('admin.users')->with('success','User updated');
    }

    public function deleteUser(\App\Models\User $user)
    {
        $user->delete();
        return redirect()->route('admin.users')->with('success','User deleted');
    }

    // Booking Monitoring
    public function bookings(Request $request)
    {
        \App\Http\Controllers\BookingController::releaseExpiredBookings();

        $query = Booking::with(['user', 'schedule', 'seat']);

        if ($request->has('status') && $request->get('status') != '') {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('search') && $request->get('search') != '') {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })->orWhereHas('schedule', function ($sq) use ($search) {
                    $sq->where('origin', 'like', "%{$search}%")
                       ->orWhere('destination', 'like', "%{$search}%");
                });
            });
        }

        $bookings = $query->latest()->get();

        // Group by payment_code to show as one transaction in admin
        $bookings = $bookings->groupBy('payment_code')->map(function ($group) {
            $first = $group->first();
            
            // Gabungkan label kursi
            $seats = $group->map(function($b) {
                $num = intval($b->seat?->seat_number);
                if (!$num) return $b->seat?->seat_number;
                $row = floor(($num - 1) / 4) + 1;
                $col = ['A', 'B', 'C', 'D'][($num - 1) % 4];
                return $num . " ($row$col)";
            })->filter()->implode(', ');

            // Ambil bukti bayar dari salah satu booking dalam grup (jika ada)
            $proof = $group->first(function($b) { return !empty($b->payment_proof); })?->payment_proof;
            $first->payment_proof = $proof;

            // Hitung total harga (Harga dasar + Kode Unik sekali saja)
            $totalBase = $group->sum(function($b) {
                return $b->total_price ?? ($b->schedule->price ?? 0);
            });
            $first->aggregated_total = $totalBase + ($first->unique_code ?? 0);
            $first->aggregated_seats = $seats;
            $first->group_count = $group->count();
            $first->related_ids = $group->pluck('id'); // Simpan buat konfirmasi massal

            return $first;
        })->values();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function verifications()
    {
        // Ambil pemesanan yang statusnya pending_payment (menunggu verifikasi)
        $bookings = Booking::with(['user', 'schedule'])
            ->where('status', 'pending_payment')
            ->latest()
            ->get();

        return view('admin.bookings.verifications', compact('bookings'));
    }

    public function confirmBookingPayment(Booking $booking)
    {
        // Approve ALL bookings with the same payment_code
        $bookings = Booking::where('payment_code', $booking->payment_code)->get();
        
        foreach ($bookings as $b) {
            if ($b->status === 'pending_payment' || $b->status === 'pending_verification') {
                $b->update(['status' => 'booked']);
            }
        }

        return redirect()->back()->with('success', count($bookings) . ' kursi berhasil dikonfirmasi sekaligus.');
    }

    public function rejectBookingPayment(Booking $booking)
    {
        // Reject ALL bookings with the same payment_code
        $bookings = Booking::where('payment_code', $booking->payment_code)->get();

        foreach ($bookings as $b) {
            if ($b->status === 'pending_payment' || $b->status === 'pending_verification') {
                $b->update(['status' => 'cancelled']);
                if ($b->seat) {
                    $b->seat->update(['status' => 'available']);
                }
            }
        }

        return redirect()->back()->with('success', count($bookings) . ' kursi berhasil ditolak sekaligus.');
    }

    // Trip Monitoring
    public function trips(Request $request)
    {
        $query = Trip::with([
            'schedule.vehicle',
            'schedule.driver',
            'schedule.bookings.user',
            'schedule.bookings.seat',
            'locations'
        ]);

        if ($request->has('status') && $request->get('status') != '') {
            $query->where('status', $request->get('status'));
        }

        $trips = $query->latest()->get();
        return view('admin.trips.index', compact('trips'));
    }

    public function editSchedule(Schedule $schedule)
    {
        $vehicles = Vehicle::all();
        $drivers = User::where('role', 'driver')->get();
        return view('admin.schedules.edit', compact('schedule', 'vehicles', 'drivers'));
    }

    public function updateSchedule(Request $request, Schedule $schedule)
    {
        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:users,id',
            'origin' => 'required',
            'destination' => 'required',
            'departure_time' => 'required|date',
        ]);

        $vehicle = Vehicle::find($request->vehicle_id);
        $oldVehicleId = $schedule->vehicle_id;

        // Check if there are bookings before changing vehicle capacity/type
        if ($oldVehicleId != $vehicle->id) {
            $hasBookings = Booking::where('schedule_id', $schedule->id)->where('status', '!=', 'cancelled')->exists();
            if ($hasBookings) {
                return redirect()->back()->withErrors(['vehicle_id' => 'Cannot change vehicle because this schedule already has active bookings.']);
            }

            // Recreate seats if no active bookings exist
            Seat::where('schedule_id', $schedule->id)->delete();
            for ($i = 1; $i <= $vehicle->capacity; $i++) {
                Seat::create([
                    'schedule_id' => $schedule->id,
                    'seat_number' => (string)$i,
                    'status' => 'available',
                ]);
            }
        }

        $schedule->update($request->all());

        return redirect()->route('admin.schedules')->with('success', 'Schedule updated successfully');
    }

    public function activeTripsLocations(Request $request)
    {
        $trips = Trip::with([
            'schedule.vehicle',
            'schedule.driver',
            'schedule.bookings' => function($q) {
                $q->where('status', '!=', 'cancelled')->with(['user', 'seat']);
            },
            'locations'
        ])->whereIn('status', ['boarding', 'on-going', 'delayed', 'arrived'])->get();

        $data = $trips->map(function ($t) {
            return [
                'id' => $t->id,
                'origin' => $t->schedule?->origin,
                'destination' => $t->schedule?->destination,
                'pickup_name' => $t->schedule?->pickup_name,
                'pickup_lat' => $t->schedule?->pickup_lat,
                'pickup_lng' => $t->schedule?->pickup_lng,
                'drop_off_name' => $t->schedule?->drop_off_name,
                'drop_off_lat' => $t->schedule?->drop_off_lat,
                'drop_off_lng' => $t->schedule?->drop_off_lng,
                'driver' => $t->schedule?->driver?->name ?? 'Driver',
                'vehicle' => $t->schedule?->vehicle?->license_plate ?? '',
                'status' => $t->status,
                'locations' => $t->locations->map(function ($loc) {
                    return [$loc->latitude, $loc->longitude];
                })->toArray(),
                'passengers' => $t->schedule->bookings->map(function ($booking) {
                    return [
                        'name' => $booking->user?->name ?? 'User',
                        'seat' => $booking->seat?->seat_number ?? $booking->seat_id,
                        'phone' => $booking->user?->phone ?? '',
                    ];
                })->toArray()
            ];
        });

        return response()->json($data);
    }
}
