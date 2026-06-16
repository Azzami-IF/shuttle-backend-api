#!/usr/bin/env php
<?php
/**
 * Setup script to organize CacheManager file
 * 
 * This script:
 * 1. Creates the Services directory if it doesn't exist
 * 2. Moves CacheManager from Providers to Services
 * 3. Verifies the file is in the correct location
 */

$basePath = __DIR__ . '/Laravel/app';
$providersPath = $basePath . '/Providers/CacheManager.php';
$servicesDir = $basePath . '/Services';
$servicesPath = $servicesDir . '/CacheManager.php';

// Create Services directory if it doesn't exist
if (!is_dir($servicesDir)) {
    if (mkdir($servicesDir, 0755, true)) {
        echo "✓ Created Services directory\n";
    } else {
        echo "✗ Failed to create Services directory\n";
        exit(1);
    }
} else {
    echo "✓ Services directory exists\n";
}

// Move file if it exists in Providers
if (file_exists($providersPath)) {
    if (copy($providersPath, $servicesPath)) {
        echo "✓ Copied CacheManager to Services directory\n";
        
        if (unlink($providersPath)) {
            echo "✓ Removed CacheManager from Providers directory\n";
        } else {
            echo "⚠ Note: CacheManager still exists in Providers (manual cleanup may be needed)\n";
        }
    } else {
        echo "✗ Failed to copy CacheManager file\n";
        exit(1);
    }
} elseif (file_exists($servicesPath)) {
    echo "✓ CacheManager already in Services directory\n";
} else {
    echo "✗ CacheManager file not found\n";
    exit(1);
}

// Verify final location
if (file_exists($servicesPath)) {
    echo "\n✓ Setup complete! CacheManager is ready at: app/Services/CacheManager.php\n";
} else {
    echo "\n✗ Setup failed! CacheManager not found at expected location\n";
    exit(1);
}
?>
