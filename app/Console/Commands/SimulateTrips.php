<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\Location;
use Illuminate\Console\Command;

class SimulateTrips extends Command
{
    protected $signature = 'trips:simulate {--interval=3 : Interval update dalam detik} {--duration= : Durasi simulasi berjalan dalam detik sebelum berhenti}';
    protected $description = 'Simulasikan pergerakan bus real-time untuk trip yang sedang berlangsung (on-going)';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $duration = $this->option('duration') ? (int) $this->option('duration') : null;
        $endTime = $duration ? time() + $duration : null;

        $this->info("Memulai simulasi pergerakan bus (Update setiap {$interval} detik)...");
        if ($duration) {
            $this->info("Simulasi akan otomatis berhenti setelah {$duration} detik.");
        }
        $this->info("Tekan Ctrl+C untuk menghentikan.");

        $coordinates = [
            'jakarta' => [-6.3090, 106.8824],
            'terminal kampung rambutan' => [-6.3090, 106.8824],
            'bandung' => [-6.9452, 107.5937],
            'terminal leuwi panjang' => [-6.9452, 107.5937],
            'karawang' => [-6.3073, 107.2913],
            'sumedang' => [-6.8524, 107.9234],
            'subang' => [-6.5715, 107.7587],
            'purwakarta' => [-6.5571, 107.4431],
            'cikampek' => [-6.4025, 107.4589],
            'cirebon' => [-6.7320, 108.5523],
            'bogor' => [-6.5971, 106.7932],
            'depok' => [-6.4025, 106.8227],
            'bekasi' => [-6.2383, 106.9756],
            'tangerang' => [-6.1702, 106.6403],
        ];

        while (true) {
            // Cek batas durasi jika ditentukan
            if ($endTime && time() >= $endTime) {
                $this->info("Durasi simulasi selesai. Keluar secara otomatis.");
                break;
            }

            // Ambil semua trip yang statusnya 'on-going' atau 'scheduled'
            $trips = Trip::whereIn('status', ['on-going', 'scheduled'])->with('schedule')->get();

            if ($trips->isEmpty()) {
                $this->comment("Menunggu trip aktif (status: scheduled atau on-going)...");
            } else {
                foreach ($trips as $trip) {
                    // Otomatis aktifkan trip scheduled ke on-going jika waktu keberangkatan sudah lewat/tiba
                    if ($trip->status === 'scheduled') {
                        if (\Carbon\Carbon::parse($trip->schedule->departure_time)->isPast()) {
                            $passengerCount = $trip->schedule->bookings()->where('status', '!=', 'cancelled')->count();
                            
                            if ($passengerCount > 0) {
                                $trip->update([
                                    'status' => 'on-going',
                                    'started_at' => now(),
                                ]);
                                event(new \App\Events\TripStarted($trip->load(['schedule.vehicle', 'schedule.driver'])));
                                $this->info("Trip #{$trip->id} ({$trip->schedule->origin} ➔ {$trip->schedule->destination}) otomatis diaktifkan menjadi ON-GOING karena memiliki {$passengerCount} penumpang!");
                            } else {
                                $trip->update(['status' => 'cancelled_empty']);
                                $this->info("Trip #{$trip->id} ({$trip->schedule->origin} ➔ {$trip->schedule->destination}) otomatis dibatalkan (cancelled_empty) karena tidak ada penumpang.");
                                continue;
                            }
                        } else {
                            // Jangan simulasikan perjalanan yang belum berangkat
                            continue;
                        }
                    }

                    $originName = strtolower(trim($trip->schedule->origin));
                    $destName = strtolower(trim($trip->schedule->destination));

                    $originCoords = $coordinates[$originName] ?? null;
                    $destCoords = $coordinates[$destName] ?? null;

                    if (!$originCoords || !$destCoords) {
                        $this->warn("Trip #{$trip->id}: Koordinat rute {$trip->schedule->origin} -> {$trip->schedule->destination} tidak terdefinisi.");
                        continue;
                    }

                    $originLat = $originCoords[0];
                    $originLng = $originCoords[1];
                    $destLat = $destCoords[0];
                    $destLng = $destCoords[1];

                    // Coba ambil rute jalan raya dari OSRM (melewati Halte/Stops jika ada)
                    $routeCoords = [];
                    try {
                        $routeStops = [
                            'depok-bandung' => [
                                [107.2913, -6.3073], // Pool Karawang [lng, lat]
                                [107.4431, -6.5571]  // Pool Purwakarta [lng, lat]
                            ],
                            'bogor-bandung' => [
                                [107.1396, -6.8242], // Pool Cianjur [lng, lat]
                                [107.4721, -6.8406]  // Pool Padalarang [lng, lat]
                            ],
                            'jakarta-bandung' => [
                                [106.9756, -6.2383], // Pool Bekasi [lng, lat]
                                [107.2913, -6.3073]  // Pool Karawang [lng, lat]
                            ]
                        ];

                        $routeKey = "{$originName}-{$destName}";
                        $waypoints = [];
                        $waypoints[] = "{$originLng},{$originLat}";
                        if (isset($routeStops[$routeKey])) {
                            foreach ($routeStops[$routeKey] as $stop) {
                                $waypoints[] = "{$stop[0]},{$stop[1]}";
                            }
                        }
                        $waypoints[] = "{$destLng},{$destLat}";
                        $waypointStr = implode(';', $waypoints);

                        $url = "https://router.project-osrm.org/route/v1/driving/{$waypointStr}?overview=full&geometries=geojson";
                        $ctx = stream_context_create([
                            "http" => [
                                "header" => "User-Agent: ShuttleAppSimulation/1.0\r\n",
                                "timeout" => 3
                            ]
                        ]);
                        $response = @file_get_contents($url, false, $ctx);
                        if ($response) {
                            $data = json_decode($response, true);
                            $routeCoords = $data['routes'][0]['geometry']['coordinates'] ?? [];
                        }
                    } catch (\Exception $e) {
                        // ignore and fallback to straight line
                    }

                    if (!empty($routeCoords)) {
                        $totalPoints = count($routeCoords);
                        $locationCount = $trip->locations()->count();
                        
                        // Selesaikan perjalanan dalam kisaran ~100 ticks untuk pergerakan lebih halus
                        $step = max(1, (int)($totalPoints / 100));
                        $index = $locationCount * $step;

                        if ($index >= $totalPoints - 1) {
                            // Sudah sampai tujuan
                            $location = Location::create([
                                'trip_id' => $trip->id,
                                'latitude' => $destLat,
                                'longitude' => $destLng,
                            ]);
                            $this->broadcastLocation($trip, $location);
                            $this->completeTrip($trip);
                            $this->info("Trip #{$trip->id} ({$trip->schedule->origin} -> {$trip->schedule->destination}) telah TIBA di tujuan!");
                        } else {
                            // Simpan titik koordinat jalan raya (OSRM mengembalikan format [lng, lat])
                            $point = $routeCoords[$index];
                            $location = Location::create([
                                'trip_id' => $trip->id,
                                'latitude' => $point[1],
                                'longitude' => $point[0],
                            ]);
                            $this->broadcastLocation($trip, $location);
                            $this->info("Trip #{$trip->id} [{$trip->schedule->origin} -> {$trip->schedule->destination}]: Update lokasi jalan raya ({$point[1]}, {$point[0]})");
                        }
                    } else {
                        // FALLBACK: Perhitungan garis lurus jika gagal fetch OSRM
                        $lastLoc = $trip->locations()->latest()->first();

                        if (!$lastLoc) {
                            $currentLat = $originLat;
                            $currentLng = $originLng;
                        } else {
                            $currentLat = $lastLoc->latitude;
                            $currentLng = $lastLoc->longitude;
                        }

                        $distance = sqrt(pow($destLat - $currentLat, 2) + pow($destLng - $currentLng, 2));

                        if ($distance < 0.005) {
                            $this->completeTrip($trip);
                            $this->info("Trip #{$trip->id} ({$trip->schedule->origin} -> {$trip->schedule->destination}) telah TIBA di tujuan!");
                            continue;
                        }

                        $nextLat = $currentLat + ($destLat - $currentLat) * 0.08;
                        $nextLng = $currentLng + ($destLng - $currentLng) * 0.08;

                        $location = Location::create([
                            'trip_id' => $trip->id,
                            'latitude' => $nextLat,
                            'longitude' => $nextLng,
                        ]);
                        $this->broadcastLocation($trip, $location);

                        $this->info("Trip #{$trip->id} [{$trip->schedule->origin} -> {$trip->schedule->destination}]: Update lokasi garis lurus ({$nextLat}, {$nextLng})");
                    }
                }
            }

            sleep($interval);
        }
    }

    private function broadcastLocation(Trip $trip, Location $location)
    {
        $schedule = $trip->schedule;
        $vehicleInfo = [];
        if ($schedule) {
            $vehicleInfo = [
                'plate_number' => $schedule->vehicle->license_plate ?? 'Unknown',
                'driver_name' => $schedule->driver->name ?? 'Unknown',
            ];
        }

        event(new \App\Events\DriverLocationUpdated(
            (int) $schedule->id,
            (float) $location->latitude,
            (float) $location->longitude,
            $location->created_at->toIso8601String(),
            $vehicleInfo
        ));
    }

    private function completeTrip(Trip $trip)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update all bookings for this schedule to completed
            $trip->schedule->bookings()->where('status', 'booked')->update(['status' => 'completed']);

            // Dispatch event to notify passengers & trigger bot return bookings
            event(new \App\Events\TripCompleted($trip->load(['schedule.vehicle', 'schedule.driver'])));
        });
    }
}
