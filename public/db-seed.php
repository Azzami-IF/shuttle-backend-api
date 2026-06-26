<?php
define('LARAVEL_START', microtime(true));

// Load composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';

// Resolve console kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Call db:seed
$status = $kernel->call('db:seed');

echo "<h1>Laravel Database Seeded Successfully!</h1>";
echo "Status: " . $status . "<br>";
echo "Output: <pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
