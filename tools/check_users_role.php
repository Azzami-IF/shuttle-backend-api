<?php
// Simple checker: bootstraps the Laravel app and reports if `users.role` exists.
// Usage: php tools/check_users_role.php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

function env_str($key, $default = null) {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

echo "DB_CONNECTION=" . env_str('DB_CONNECTION', 'unknown') . PHP_EOL;
echo "DB_DATABASE=" . env_str('DB_DATABASE', '') . PHP_EOL;

try {
    $exists = Schema::hasColumn('users', 'role');
    if ($exists) {
        echo "OK: column `users.role` exists\n";
        exit(0);
    }
    echo "MISSING: column `users.role` not found\n";
    exit(2);
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    exit(3);
}
