<?php

namespace App\Console\Commands;

use App\Models\RouteTemplate;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSchedules extends Command
{
    protected $signature   = 'schedules:generate {--days=30 : Berapa hari ke depan}';
    protected $description = 'Auto-generate jadwal dari Route Templates';

    public function handle(): void
    {
        $daysAhead = (int) $this->option('days');
        $templates = RouteTemplate::where('is_active', true)->with(['vehicle', 'driver'])->get();

        if ($templates->isEmpty()) {
            $this->warn('Tidak ada Route Template aktif.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            $hood = min($daysAhead, $template->generate_days_ahead);

            for ($i = 0; $i <= $hood; $i++) {
                $date     = Carbon::today()->addDays($i);
                $dayOfWeek = (int) $date->format('w'); // 0=Minggu, 6=Sabtu

                // Cek apakah hari ini aktif di template
                if (!in_array($dayOfWeek, $template->active_days)) {
                    $skipped++;
                    continue;
                }

                // Gabung tanggal + jam keberangkatan
                [$h, $m, $s] = explode(':', $template->departure_time);
                $departureTime = $date->copy()->setTime((int)$h, (int)$m, (int)$s);

                // Skip kalau jadwalnya sudah lewat
                if ($departureTime->isPast()) {
                    $skipped++;
                    continue;
                }

                // Cek apakah jadwal ini sudah ada (hindari duplikat)
                $exists = Schedule::where('vehicle_id',      $template->vehicle_id)
                    ->where('origin',         $template->origin)
                    ->where('destination',    $template->destination)
                    ->where('departure_time', $departureTime)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Buat jadwal baru
                DB::transaction(function () use ($template, $departureTime, &$created) {
                    $schedule = Schedule::create([
                        'vehicle_id'     => $template->vehicle_id,
                        'driver_id'      => $template->driver_id,
                        'origin'         => $template->origin,
                        'destination'    => $template->destination,
                        'departure_time' => $departureTime,
                        'price'          => $template->price,
                    ]);

                    // Buat kursi berdasarkan kapasitas kendaraan
                    $capacity = $template->vehicle->capacity ?? 12;
                    for ($seat = 1; $seat <= $capacity; $seat++) {
                        Seat::create([
                            'schedule_id' => $schedule->id,
                            'seat_number' => (string)$seat,
                            'status'      => 'available',
                        ]);
                    }

                    // Buat Trip record awal
                    Trip::create([
                        'schedule_id' => $schedule->id,
                        'status'      => 'scheduled',
                    ]);

                    $created++;
                });
            }
        }

        $this->info("✅ Selesai! $created jadwal dibuat, $skipped dilewati.");
        Log::info("GenerateSchedules: $created created, $skipped skipped.");
    }
}
