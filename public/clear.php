<?php
define('LARAVEL_START', microtime(true));

// Load composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';

// Resolve console kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Call optimize:clear
$status = $kernel->call('optimize:clear');

echo "<h1>Laravel Cache Cleared!</h1>";
echo "Status: " . $status . "<br>";
echo "Output: <pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
