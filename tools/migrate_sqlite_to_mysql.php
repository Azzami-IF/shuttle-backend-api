<?php
/**
 * Migrate data from current default DB (likely sqlite) to a MySQL target.
 * Usage (from project root):
 *
 * MIGRATE_TARGET_DB_HOST=127.0.0.1 \
 * MIGRATE_TARGET_DB_PORT=3306 \
 * MIGRATE_TARGET_DB_DATABASE=ambt1462_shuttle \
 * MIGRATE_TARGET_DB_USERNAME=ambt1462_ambatu \
 * MIGRATE_TARGET_DB_PASSWORD='secret' \
 * php tools/migrate_sqlite_to_mysql.php
 *
 * The script will:
 *  - read tables from the default connection
 *  - create a runtime connection to the target MySQL
 *  - copy rows table-by-table (disables foreign key checks during import)
 *  - set AUTO_INCREMENT on target tables to max(id)+1 when possible
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

function env_or($k, $def = null) { $v = getenv($k); return $v === false ? $def : $v; }

$target = [
    'driver' => 'mysql',
    'host' => env_or('MIGRATE_TARGET_DB_HOST', '127.0.0.1'),
    'port' => env_or('MIGRATE_TARGET_DB_PORT', '3306'),
    'database' => env_or('MIGRATE_TARGET_DB_DATABASE'),
    'username' => env_or('MIGRATE_TARGET_DB_USERNAME'),
    'password' => env_or('MIGRATE_TARGET_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
];

if (empty($target['database']) || empty($target['username'])) {
    echo "ERROR: set MIGRATE_TARGET_DB_DATABASE and MIGRATE_TARGET_DB_USERNAME (and password)\n";
    exit(2);
}

// register runtime connection
Config::set('database.connections.migrate_target', $target);

$sourceConn = DB::connection(); // default connection (sqlite)
$targetConn = DB::connection('migrate_target');

echo "Source connection: " . $sourceConn->getName() . "\n";
echo "Target connection: migrate_target -> mysql://{$target['username']}@{$target['host']}/{$target['database']}\n";

// get tables from sqlite compatible way
try {
    $driver = strtolower($sourceConn->getConfig('driver'));
    if ($driver === 'sqlite') {
        $tables = $sourceConn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = array_map(fn($r) => $r->name, $tables);
    } else {
        // generic information_schema approach
        $dbName = $sourceConn->getDatabaseName();
        $rows = $sourceConn->select("SELECT table_name as name FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
        $tables = array_map(fn($r) => $r->name, $rows);
    }
} catch (Throwable $e) {
    echo "ERROR reading tables: " . $e->getMessage() . "\n";
    exit(3);
}

if (empty($tables)) {
    echo "No tables found on source connection.\n";
    exit(0);
}

echo "Tables to migrate: " . implode(', ', $tables) . "\n";

// confirm
echo "WARNING: this will insert into target DB. Continue? Type 'yes' and enter: ";
$handle = fopen('php://stdin','r');
$line = trim(fgets($handle));
if ($line !== 'yes') {
    echo "Aborted by user.\n";
    exit(1);
}

// disable foreign key checks on target
$targetConn->statement('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    echo "Migrating table: $table ... ";
    try {
        $count = $sourceConn->table($table)->count();
        echo "($count rows) ";
        if ($count === 0) { echo "skipped\n"; continue; }

        $batch = 0;
        $sourceConn->table($table)->orderBy('rowid')->chunk(500, function($rows) use ($targetConn, $table, &$batch) {
            // $rows is a Collection; convert to array of arrays for insert
            $arr = $rows->toArray();
            $inserts = array_map(function($r){ return (array)$r; }, $arr);
            if (!empty($inserts)) {
                try {
                    // prefer insertOrIgnore to avoid duplicate-key interrupts
                    $targetConn->table($table)->insertOrIgnore($inserts);
                } catch (Throwable $_) {
                    $targetConn->table($table)->insert($inserts);
                }
            }
            $batch++;
            echo ".";
            flush();
        });

        // try to set AUTO_INCREMENT if `id` column exists
        try {
            $max = $targetConn->table($table)->max('id');
            if ($max) {
                $targetConn->statement("ALTER TABLE `$table` AUTO_INCREMENT = ?", [intval($max)+1]);
            }
        } catch (Throwable $_) {
            // ignore
        }

        echo " done\n";
    } catch (Throwable $e) {
        echo "ERROR migrating table $table: " . $e->getMessage() . "\n";
    }
}

// re-enable foreign key checks
$targetConn->statement('SET FOREIGN_KEY_CHECKS=1');

echo "Migration finished.\n";
