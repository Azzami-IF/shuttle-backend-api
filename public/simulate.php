<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('LARAVEL_START', microtime(true));

// Setup global error handling to debug online issues
try {
    // Load composer autoloader
    require __DIR__.'/../vendor/autoload.php';

    // Bootstrap Laravel
    $app = require_once __DIR__.'/../bootstrap/app.php';

    // Resolve console kernel to use Artisan
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // Import models
    $tripClass = 'App\Models\Trip';
    $locationClass = 'App\Models\Location';
    
    // Handle Actions (POST)
    $message = '';
    $messageType = 'success'; // success, error, info
    $outputLog = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'set_status' && isset($_POST['trip_id'], $_POST['status'])) {
                $tripId = (int)$_POST['trip_id'];
                $status = $_POST['status'];
                $trip = $tripClass::find($tripId);
                
                if ($trip) {
                    if ($status === 'scheduled') {
                        // Reset locations when setting back to scheduled
                        $trip->locations()->delete();
                        $trip->update([
                            'status' => 'scheduled',
                            'started_at' => null,
                            'completed_at' => null
                        ]);
                        $message = "Trip #{$tripId} berhasil direset ke SCHEDULED (lokasi dihapus).";
                    } elseif ($status === 'on-going') {
                        $trip->update([
                            'status' => 'on-going',
                            'started_at' => now(),
                            'completed_at' => null
                        ]);
                        $message = "Trip #{$tripId} berhasil diubah ke ON-GOING.";
                    } elseif ($status === 'completed') {
                        $trip->update([
                            'status' => 'completed',
                            'completed_at' => now()
                        ]);
                        $message = "Trip #{$tripId} berhasil diubah ke COMPLETED.";
                    }
                } else {
                    $message = "Trip #{$tripId} tidak ditemukan.";
                    $messageType = 'error';
                }
            } 
            
            elseif ($action === 'run_tick') {
                // Run artisan trips:simulate with 1s duration and 1s interval (runs exactly 1 tick)
                $status = \Illuminate\Support\Facades\Artisan::call('trips:simulate', [
                    '--duration' => 1,
                    '--interval' => 1
                ]);
                $outputLog = \Illuminate\Support\Facades\Artisan::output();
                $message = "Berhasil menjalankan 1 tick simulasi.";
            } 
            
            elseif ($action === 'run_background') {
                // Check if exec or shell_exec is enabled
                if (function_exists('shell_exec')) {
                    $artisanPath = base_path('artisan');
                    // Run trips:simulate in the background for 1 hour (3600 seconds)
                    $cmd = "php {$artisanPath} trips:simulate --duration=3600 --interval=3 > /dev/null 2>&1 &";
                    shell_exec($cmd);
                    $message = "Simulasi latar belakang (background) berhasil dimulai selama 1 Jam! Rute bus Anda akan diperbarui setiap 3 detik.";
                    $messageType = 'info';
                } else {
                    $message = "Fungsi 'shell_exec' dinonaktifkan di server hosting Anda. Silakan gunakan tombol 'Jalankan 1 Tick' secara manual atau jalankan via cron job.";
                    $messageType = 'error';
                }
            }
            
            elseif ($action === 'kill_simulation') {
                if (function_exists('shell_exec')) {
                    // Kill any running php artisan trips:simulate processes
                    // In Linux, we can kill by searching process name
                    shell_exec("pkill -f 'trips:simulate'");
                    $message = "Mencoba mematikan semua proses simulator 'trips:simulate' di server.";
                    $messageType = 'info';
                } else {
                    $message = "Fungsi 'shell_exec' dinonaktifkan di server hosting Anda.";
                    $messageType = 'error';
                }
            }
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }

    // Fetch current active trips
    $activeTrips = $tripClass::with(['schedule.driver', 'schedule.vehicle'])
        ->whereIn('status', ['scheduled', 'on-going'])
        ->orderBy('status', 'desc')
        ->orderBy('id', 'asc')
        ->get();

    // Fetch completed trips for logs
    $completedTrips = $tripClass::with(['schedule.driver', 'schedule.vehicle'])
        ->where('status', 'completed')
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

} catch (\Throwable $err) {
    echo "<div style='font-family: sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px;'>";
    echo "<h2 style='margin-top: 0;'>System Initialization Error</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($err->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($err->getFile()) . " (Line " . $err->getLine() . ")</p>";
    echo "<p><strong>Trace:</strong></p>";
    echo "<pre style='background: #fef2f2; padding: 10px; border: 1px solid #fecaca; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 13px;'>" . htmlspecialchars($err->getTraceAsString()) . "</pre>";
    echo "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KemanapunGo - Trip Simulator Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-secondary: #131c2e;
            --bg-card: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.4);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.4);
            --warning: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.4);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem 1rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0px, transparent 50%);
            background-attachment: fixed;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-area {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--success));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(to right, #3b82f6, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.5rem;
        }

        /* Alert styling */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: fadeIn 0.3s ease-out;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        /* Layout Grid */
        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card panels */
        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        /* Badge status */
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-scheduled {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-ongoing {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-completed {
            background-color: rgba(148, 163, 184, 0.15);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        /* Controls Panel */
        .controls-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .btn {
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .btn-secondary {
            background-color: rgba(51, 65, 85, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: rgba(51, 65, 85, 0.9);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .trip-route {
            font-weight: 600;
            color: var(--text-primary);
        }

        .trip-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .action-cell {
            display: flex;
            gap: 0.5rem;
        }

        /* Console Output window */
        .console {
            background-color: #05070c;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
            color: #34d399;
            max-height: 250px;
            overflow-y: auto;
            white-space: pre-wrap;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.8);
        }

        .console-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 0.25rem;
        }

        /* Footer styling */
        footer {
            text-align: center;
            margin-top: 4rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }

        footer a {
            color: var(--primary);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-area">
                <div class="logo-icon">K</div>
                <h1>KemanapunGo</h1>
            </div>
            <div class="subtitle">Real-time GPS Trip Simulator Controller Dashboard (VPS/Online)</div>
        </header>

        <!-- Notification Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div>
                    <strong><?= $messageType === 'error' ? 'Gagal:' : ($messageType === 'info' ? 'Info:' : 'Sukses:') ?></strong> 
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Left Side: Trip Table & Logs -->
            <div>
                <!-- Active Trips Section -->
                <div class="card">
                    <div class="card-title">
                        <span>Daftar Perjalanan Aktif</span>
                        <span class="badge badge-ongoing"><?= count($activeTrips) ?> Berjalan/Terjadwal</span>
                    </div>
                    <div class="table-container">
                        <?php if ($activeTrips->isEmpty()): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                Tidak ada trip berstatus scheduled atau on-going saat ini.<br>
                                <a href="db-seed.php" class="btn btn-secondary btn-sm" style="margin-top: 1rem;">Lakukan DB Seed Sekarang</a>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Trip ID</th>
                                        <th>Rute & Armada</th>
                                        <th>Status</th>
                                        <th>GPS Ticks</th>
                                        <th>Aksi Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeTrips as $trip): ?>
                                        <tr>
                                            <td><strong>#<?= $trip->id ?></strong></td>
                                            <td>
                                                <div class="trip-route"><?= htmlspecialchars($trip->schedule->origin) ?> ➔ <?= htmlspecialchars($trip->schedule->destination) ?></div>
                                                <div class="trip-info">
                                                    Supir: <?= htmlspecialchars($trip->schedule->driver->name ?? 'Tidak Ada') ?><br>
                                                    Bus: <?= htmlspecialchars($trip->schedule->vehicle->name ?? 'Tidak Ada') ?> (<?= htmlspecialchars($trip->schedule->vehicle->license_plate ?? '-') ?>)
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $trip->status === 'on-going' ? 'ongoing' : 'scheduled' ?>">
                                                    <?= $trip->status ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-family: monospace; font-weight: bold; color: var(--success);">
                                                    <?= $trip->locations()->count() ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-cell">
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="set_status">
                                                        <input type="hidden" name="trip_id" value="<?= $trip->id ?>">
                                                        
                                                        <?php if ($trip->status === 'scheduled'): ?>
                                                            <input type="hidden" name="status" value="on-going">
                                                            <button type="submit" class="btn btn-success btn-sm">Mulai (On-Going)</button>
                                                        <?php elseif ($trip->status === 'on-going'): ?>
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="btn btn-secondary btn-sm">Selesaikan</button>
                                                        <?php endif; ?>
                                                    </form>
                                                    
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="set_status">
                                                        <input type="hidden" name="trip_id" value="<?= $trip->id ?>">
                                                        <input type="hidden" name="status" value="scheduled">
                                                        <button type="submit" class="btn btn-danger btn-sm btn-secondary" title="Reset Trip ke Scheduled & hapus lokasi koordinat">Reset</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Console Output Log from Artisan -->
                <?php if ($outputLog): ?>
                    <div class="card">
                        <div class="card-title">Console Output (Artisan)</div>
                        <div class="console-header">
                            <span>Artisan CLI Output</span>
                            <span>SUCCESS</span>
                        </div>
                        <div class="console"><?= htmlspecialchars($outputLog) ?></div>
                    </div>
                <?php endif; ?>
                
                <!-- Completed Trips Section (Log) -->
                <div class="card">
                    <div class="card-title">Riwayat Perjalanan Selesai (5 Terakhir)</div>
                    <div class="table-container">
                        <?php if ($completedTrips->isEmpty()): ?>
                            <div style="text-align: center; padding: 1rem; color: var(--text-secondary);">
                                Belum ada riwayat perjalanan selesai.
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Trip ID</th>
                                        <th>Rute</th>
                                        <th>Supir & Kendaraan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedTrips as $trip): ?>
                                        <tr>
                                            <td>#<?= $trip->id ?></td>
                                            <td><?= htmlspecialchars($trip->schedule->origin) ?> ➔ <?= htmlspecialchars($trip->schedule->destination) ?></td>
                                            <td>
                                                <?= htmlspecialchars($trip->schedule->driver->name ?? 'Tidak Ada') ?><br>
                                                <small style="color: var(--text-secondary)"><?= htmlspecialchars($trip->schedule->vehicle->name ?? '-') ?></small>
                                            </td>
                                            <td><span class="badge badge-completed"><?= $trip->status ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Controls Info -->
            <div>
                <div class="card">
                    <div class="card-title">Simulator Control Panel</div>
                    <div class="controls-grid">
                        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.5;">
                            Gunakan tombol-tombol di bawah ini untuk memperbarui pergerakan bus secara real-time langsung dari web browser.
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="run_tick">
                            <button type="submit" class="btn btn-success" style="width: 100%; margin-bottom: 0.75rem;">
                                🚗 Jalankan 1 Tick Simulasi
                            </button>
                        </form>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
                            <strong>Rekomendasi:</strong> Menggerakkan semua bus aktif 1 langkah ke depan sepanjang rute jalan raya. Tekan berulang kali untuk melihat pergerakan bus di peta.
                        </p>

                        <form method="POST">
                            <input type="hidden" name="action" value="run_background">
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 0.75rem;">
                                🔄 Jalankan Latar Belakang (1 Jam)
                            </button>
                        </form>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
                            Memulai proses simulator di server yang berjalan secara otomatis selama 1 jam. Bus akan terus berjalan sendiri tanpa perlu Anda menekan tombol.
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="kill_simulation">
                            <button type="submit" class="btn btn-secondary btn-danger" style="width: 100%;">
                                🛑 Hentikan Semua Simulator
                            </button>
                        </form>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                            Menghentikan semua proses simulator yang berjalan di latar belakang server.
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">Petunjuk Penggunaan</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
                        <ol style="padding-left: 1.2rem;">
                            <li style="margin-bottom: 0.5rem;">Pastikan Anda sudah menjalankan database seeding di <a href="db-seed.php" target="_blank" style="color: var(--success); font-weight:600;">db-seed.php</a> agar jadwal tersedia.</li>
                            <li style="margin-bottom: 0.5rem;">Jika status trip masih <strong>scheduled</strong>, klik tombol <strong>Mulai (On-Going)</strong> pada trip tersebut agar simulator memproses pergerakan busnya.</li>
                            <li style="margin-bottom: 0.5rem;">Klik tombol <strong>🚗 Jalankan 1 Tick</strong> untuk memperbarui posisi bus secara instan, atau klik <strong>🔄 Jalankan Latar Belakang</strong> agar server menggerakkan bus otomatis setiap 3 detik.</li>
                            <li style="margin-bottom: 0.5rem;">Buka peta admin di Dashboard Monitoring Admin atau halaman customer di aplikasi Ionic untuk melihat bus bergerak secara langsung di peta!</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>KemanapunGo Trip Simulator &copy; 2026. Dikembangkan untuk kemudahan testing VPS & Online Hosting.</p>
        </footer>
    </div>
</body>
</html>
