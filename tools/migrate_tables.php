<?php
/**
 * Migrate only specified tables from source (default) to `migrate_target` connection.
 * Usage (from project root):
 *
 * MIGRATE_TARGET_DB_HOST=127.0.0.1 \
 * MIGRATE_TARGET_DB_PORT=3306 \
 * MIGRATE_TARGET_DB_DATABASE=ambt1462_shuttle \
 * MIGRATE_TARGET_DB_USERNAME=ambt1462_ambatu \
 * MIGRATE_TARGET_DB_PASSWORD='secret' \
 * php tools/migrate_tables.php bookings,schedules,users
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

Config::set('database.connections.migrate_target', $target);

$source = DB::connection();
$targetConn = DB::connection('migrate_target');

$args = $argv;
array_shift($args);
if (empty($args)) {
    echo "Usage: php tools/migrate_tables.php table1,table2 or list tables as args\n";
    exit(1);
}

$list = explode(',', $args[0]);
$tables = array_map('trim', $list);

echo "Migrating tables: " . implode(', ', $tables) . "\n";

$targetConn->statement('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    echo "Processing $table ... ";
    try {
        $count = $source->table($table)->count();
        echo "($count rows) ";
        if ($count === 0) { echo "skipped\n"; continue; }

        $source->table($table)->orderBy('rowid')->chunk(500, function($rows) use ($targetConn, $table) {
            $arr = $rows->toArray();
            $inserts = array_map(function($r){ return (array)$r; }, $arr);
            if (!empty($inserts)) {
                try {
                    $targetConn->table($table)->insertOrIgnore($inserts);
                } catch (Throwable $_) {
                    $targetConn->table($table)->insert($inserts);
                }
            }
            echo ".";
            flush();
        });

        echo " done\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

$targetConn->statement('SET FOREIGN_KEY_CHECKS=1');

echo "Done.\n";
