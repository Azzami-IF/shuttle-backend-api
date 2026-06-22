<?php
// Verify migration: compare row counts between source (default) and migrate_target (MySQL)
// Usage:
// MIGRATE_TARGET_DB_HOST=127.0.0.1 \
// MIGRATE_TARGET_DB_PORT=3306 \
// MIGRATE_TARGET_DB_DATABASE=ambt1462_shuttle \
// MIGRATE_TARGET_DB_USERNAME=ambt1462_ambatu \
// MIGRATE_TARGET_DB_PASSWORD='secret' \
// php tools/verify_migration.php

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

echo "Source connection: " . $source->getName() . "\n";
echo "Target connection: migrate_target -> mysql://{$target['username']}@{$target['host']}/{$target['database']}\n";

// get tables
try {
    $driver = strtolower($source->getConfig('driver'));
    if ($driver === 'sqlite') {
        $rows = $source->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = array_map(fn($r) => $r->name, $rows);
    } else {
        $dbName = $source->getDatabaseName();
        $rows = $source->select("SELECT table_name as name FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
        $tables = array_map(fn($r) => $r->name, $rows);
    }
} catch (Throwable $e) {
    echo "ERROR reading tables: " . $e->getMessage() . "\n";
    exit(3);
}

if (empty($tables)) { echo "No tables found on source.\n"; exit(0); }

$mismatches = [];
foreach ($tables as $table) {
    try {
        $srcCount = $source->table($table)->count();
    } catch (Throwable $e) {
        echo "[SRC] $table : ERROR ({$e->getMessage()})\n";
        $srcCount = null;
    }

    try {
        $tcount = $targetConn->select("SHOW TABLES LIKE ?", [$table]);
        if (empty($tcount)) {
            $tcountLabel = 'MISSING';
            $tCount = null;
        } else {
            $tCount = $targetConn->table($table)->count();
            $tcountLabel = $tCount;
        }
    } catch (Throwable $e) {
        $tcountLabel = 'ERROR';
        $tCount = null;
    }

    $ok = ($srcCount === $tCount);
    $status = $ok ? 'OK' : 'DIFF';
    printf("%-30s src=%6s target=%8s  %s\n", $table, $srcCount ?? 'ERR', $tcountLabel, $status);
    if (!$ok) $mismatches[] = $table;
}

if (!empty($mismatches)) {
    echo "\nMISMATCHED TABLES: " . implode(', ', $mismatches) . "\n";
    exit(5);
}

echo "\nAll table counts match.\n";
exit(0);
