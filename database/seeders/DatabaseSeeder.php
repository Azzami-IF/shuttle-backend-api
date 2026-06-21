<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Trip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Disable foreign keys and truncate transactional tables
        Schema::disableForeignKeyConstraints();
        DB::table('locations')->truncate();
        DB::table('trips')->truncate();
        DB::table('bookings')->truncate();
        DB::table('seats')->truncate();
        DB::table('schedules')->truncate();
        Schema::enableForeignKeyConstraints();

        // Admin
        User::updateOrCreate(
            ['email' => 'admin@shuttle.com'],
            [
                'name' => 'Admin System',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // Customers
        $customerData = [
            ['email' => 'alice@gmail.com', 'name' => 'Alice Customer'],
            ['email' => 'bob@gmail.com', 'name' => 'Bob Customer'],
            ['email' => 'charlie@gmail.com', 'name' => 'Charlie Customer'],
            ['email' => 'david@gmail.com', 'name' => 'David Customer'],
            ['email' => 'eva@gmail.com', 'name' => 'Eva Customer'],
        ];

        $customers = [];
        foreach ($customerData as $c) {
            $user = User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'password' => Hash::make('password'),
                    'role' => 'customer',
                ]
            );
            $customers[] = $user;
        }

        // Drivers (ambil yang ada di database atau buat jika kosong)
        $drivers = User::where('role', 'driver')->get();
        if ($drivers->isEmpty()) {
            $driversData = [
                ['email' => 'driver1@shuttle.com', 'name' => 'Ahmad Driver'],
                ['email' => 'driver2@shuttle.com', 'name' => 'Budiman Driver'],
                ['email' => 'driver3@shuttle.com', 'name' => 'Cecep Driver'],
                ['email' => 'driver4@shuttle.com', 'name' => 'Dani Driver'],
                ['email' => 'driver5@shuttle.com', 'name' => 'Eka Driver'],
            ];
            foreach ($driversData as $d) {
                User::updateOrCreate(
                    ['email' => $d['email']],
                    [
                        'name' => $d['name'],
                        'password' => Hash::make('password'),
                        'role' => 'driver',
                    ]
                );
            }
            $drivers = User::where('role', 'driver')->get();
        }

        // Vehicles (ambil yang ada di database atau buat jika kosong)
        $vehicles = Vehicle::all();
        if ($vehicles->isEmpty()) {
            $vehiclesData = [
                ['license_plate' => 'B 1234 ABC', 'name' => 'Kemanapun Express 01', 'capacity' => 12],
                ['license_plate' => 'D 5678 XYZ', 'name' => 'Kemanapun Express 02', 'capacity' => 12],
                ['license_plate' => 'F 9012 EFG', 'name' => 'Kemanapun Express 03', 'capacity' => 10],
                ['license_plate' => 'T 3456 HIJ', 'name' => 'Kemanapun Express 04', 'capacity' => 10],
                ['license_plate' => 'Z 7890 KLM', 'name' => 'Kemanapun Express 05', 'capacity' => 8],
            ];
            foreach ($vehiclesData as $v) {
                Vehicle::updateOrCreate(
                    ['license_plate' => $v['license_plate']],
                    [
                        'name' => $v['name'],
                        'capacity' => $v['capacity'],
                    ]
                );
            }
            $vehicles = Vehicle::all();
        }

        // Route templates to generate
        $routesList = [
            ['origin' => 'Jakarta', 'destination' => 'Bandung', 'price' => 120000, 'hours' => 2],
            ['origin' => 'Bandung', 'destination' => 'Jakarta', 'price' => 120000, 'hours' => 4],
            ['origin' => 'Bekasi', 'destination' => 'Bandung', 'price' => 110000, 'hours' => 6],
            ['origin' => 'Bogor', 'destination' => 'Bandung', 'price' => 130000, 'hours' => 8],
            ['origin' => 'Depok', 'destination' => 'Bandung', 'price' => 125000, 'hours' => 10],
            ['origin' => 'Karawang', 'destination' => 'Bandung', 'price' => 95000, 'hours' => 12],
            ['origin' => 'Bandung', 'destination' => 'Cirebon', 'price' => 100000, 'hours' => 14],
        ];

        // Konversi collection ke array agar mudah di-index secara berputar
        $driversArray = $drivers->all();
        $vehiclesArray = $vehicles->all();

        // Coordinates configuration map
        $coordinatesMap = [
            'jakarta' => ['name' => 'Terminal Kampung Rambutan', 'lat' => -6.3090, 'lng' => 106.8824],
            'bandung' => ['name' => 'Terminal Leuwi Panjang', 'lat' => -6.9452, 'lng' => 107.5937],
            'karawang' => ['name' => 'Pool Karawang', 'lat' => -6.3073, 'lng' => 107.2913],
            'sumedang' => ['name' => 'Pool Sumedang', 'lat' => -6.8524, 'lng' => 107.9234],
            'subang' => ['name' => 'Pool Subang', 'lat' => -6.5715, 'lng' => 107.7587],
            'purwakarta' => ['name' => 'Pool Purwakarta', 'lat' => -6.5571, 'lng' => 107.4431],
            'cikampek' => ['name' => 'Pool Cikampek', 'lat' => -6.4025, 'lng' => 107.4589],
            'cirebon' => ['name' => 'Terminal Harjamukti', 'lat' => -6.7320, 'lng' => 108.5523],
            'bogor' => ['name' => 'Terminal Baranangsiang', 'lat' => -6.5971, 'lng' => 106.7932],
            'depok' => ['name' => 'Pool Margonda (Depok Town Square)', 'lat' => -6.4025, 'lng' => 106.8227],
            'bekasi' => ['name' => 'Terminal Bekasi', 'lat' => -6.2383, 'lng' => 106.9756],
            'tangerang' => ['name' => 'Terminal Poris Plawad', 'lat' => -6.1702, 'lng' => 106.6403],
        ];

        $baseRoutes = [
            ['origin' => 'Depok', 'destination' => 'Bandung', 'price' => 125000, 'hour' => 8], // 08:00
            ['origin' => 'Jakarta', 'destination' => 'Bandung', 'price' => 120000, 'hour' => 12], // 12:00
            ['origin' => 'Bogor', 'destination' => 'Bandung', 'price' => 130000, 'hour' => 16], // 16:00
        ];

        // Seed for 7 days (today and next 6 days)
        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $targetDate = now()->addDays($dayOffset);
            
            foreach ($baseRoutes as $rIndex => $r) {
                // Determine driver and vehicle
                $vehicle = $vehiclesArray[($dayOffset * 3 + $rIndex) % count($vehiclesArray)];
                $driver = $driversArray[($dayOffset * 3 + $rIndex) % count($driversArray)];
                
                $originKey = strtolower(trim($r['origin']));
                $destKey = strtolower(trim($r['destination']));
                
                $originDetails = $coordinatesMap[$originKey] ?? ['name' => 'Pool ' . $r['origin'], 'lat' => -6.4025, 'lng' => 106.8227];
                $destDetails = $coordinatesMap[$destKey] ?? ['name' => 'Pool ' . $r['destination'], 'lat' => -6.9452, 'lng' => 107.5937];
                
                $departureTime = $targetDate->copy()->setTime($r['hour'], 0, 0);

                $schedule = Schedule::create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'origin' => $r['origin'],
                    'destination' => $r['destination'],
                    'departure_time' => $departureTime,
                    'price' => $r['price'],
                    'pickup_name' => $originDetails['name'],
                    'pickup_lat' => $originDetails['lat'],
                    'pickup_lng' => $originDetails['lng'],
                    'drop_off_name' => $destDetails['name'],
                    'drop_off_lat' => $destDetails['lat'],
                    'drop_off_lng' => $destDetails['lng'],
                ]);

                // Seats for schedule
                $seats = [];
                for ($i = 1; $i <= $vehicle->capacity; $i++) {
                    $seat = Seat::create([
                        'schedule_id' => $schedule->id,
                        'seat_number' => (string)$i,
                        'status' => 'available',
                    ]);
                    $seats[] = $seat;
                }

                // Trip for schedule
                Trip::create([
                    'schedule_id' => $schedule->id,
                    'status' => 'scheduled',
                ]);

                // Buat booking tiket palsu (2-4 booking per jadwal) agar bus terisi penumpang
                $numBookings = rand(2, 4);
                $paymentCode = 'TRF' . strtoupper(bin2hex(random_bytes(4)));
                $uniqueCode = rand(100, 999);
                
                $shuffledSeats = array_slice($seats, 0, $numBookings);
                foreach ($shuffledSeats as $seatIndex => $seat) {
                    $customer = $customers[$seatIndex % count($customers)];
                    
                    \App\Models\Booking::create([
                        'user_id' => $customer->id,
                        'schedule_id' => $schedule->id,
                        'seat_id' => $seat->id,
                        'status' => 'booked',
                        'payment_code' => $paymentCode,
                        'unique_code' => $uniqueCode,
                    ]);

                    $seat->update(['status' => 'booked']);
                }
            }
        }
    }
}
