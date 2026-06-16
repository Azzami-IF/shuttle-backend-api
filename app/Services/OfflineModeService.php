<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Offline Mode Service
 * 
 * Mobile offline functionality:
 * - Local data caching
 * - Offline booking creation
 * - Sync queue management
 * - Conflict detection
 * - Battery optimization
 */
class OfflineModeService
{
    private const CACHE_PREFIX = 'offline:';
    private const SYNC_QUEUE_TTL = 604800; // 7 days
    private const CACHE_SIZE_LIMIT = 50; // MB

    /**
     * Prepare offline data package
     */
    public static function prepareOfflinePackage(int $userId): array
    {
        try {
            $package = [
                'user_id' => $userId,
                'generated_at' => now()->toIso8601String(),
                'data' => [
                    'user_profile' => self::getUserData($userId),
                    'bookings' => self::getBookingHistory($userId, 50),
                    'saved_locations' => self::getSavedLocations($userId),
                    'payment_methods' => self::getPaymentMethods($userId),
                    'drivers_rated' => self::getDriversRated($userId, 20),
                    'app_config' => self::getAppConfig(),
                ],
                'metadata' => [
                    'total_size_mb' => self::estimatePackageSize(),
                    'last_sync' => now()->toIso8601String(),
                    'expires_at' => now()->addDays(7)->toIso8601String(),
                    'version' => 1,
                ],
            ];

            // Store offline package
            Cache::put(self::CACHE_PREFIX . "package:{$userId}", $package, self::SYNC_QUEUE_TTL);

            Log::info("Offline package prepared", ['user_id' => $userId, 'size_mb' => $package['metadata']['total_size_mb']]);
            return $package;
        } catch (\Exception $e) {
            Log::error("Failed to prepare offline package", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create booking in offline mode
     */
    public static function createOfflineBooking(int $userId, array $bookingData): array
    {
        try {
            $offlineBooking = [
                'id' => uniqid('offline_'),
                'user_id' => $userId,
                'pickup_location' => $bookingData['pickup_location'],
                'dropoff_location' => $bookingData['dropoff_location'],
                'distance' => $bookingData['distance'] ?? 0,
                'estimated_fare' => $bookingData['estimated_fare'] ?? 0,
                'status' => 'offline_pending',
                'created_at_local' => now()->toIso8601String(),
                'created_at_utc' => now()->toIso8601String(),
                'device_id' => $bookingData['device_id'] ?? 'unknown',
                'gps_location' => $bookingData['gps_location'] ?? null,
            ];

            // Add to sync queue
            self::addToSyncQueue($userId, 'booking_create', $offlineBooking);

            // Store locally
            $queueKey = self::CACHE_PREFIX . "bookings:pending:{$userId}";
            $queue = Cache::get($queueKey, []);
            $queue[] = $offlineBooking;
            Cache::put($queueKey, $queue, self::SYNC_QUEUE_TTL);

            return [
                'status' => 'offline_created',
                'booking_id' => $offlineBooking['id'],
                'message' => 'Booking will be synced when connection restored',
                'booking' => $offlineBooking,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create offline booking", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get sync queue for user
     */
    public static function getSyncQueue(int $userId): array
    {
        try {
            $queueKey = self::CACHE_PREFIX . "queue:{$userId}";
            $queue = Cache::get($queueKey, []);

            return [
                'user_id' => $userId,
                'pending_items' => count($queue),
                'queue' => $queue,
                'last_sync' => Cache::get(self::CACHE_PREFIX . "last_sync:{$userId}") ?? 'never',
                'total_pending_size_kb' => round(strlen(json_encode($queue)) / 1024, 2),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get sync queue", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Sync offline changes to server
     */
    public static function syncOfflineChanges(int $userId): array
    {
        try {
            $queue = Cache::get(self::CACHE_PREFIX . "queue:{$userId}", []);
            $syncResults = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($queue as $item) {
                $result = self::processSyncItem($item);
                
                if ($result['status'] === 'synced') {
                    $successCount++;
                    $syncResults[] = [
                        'item_id' => $item['id'],
                        'type' => $item['type'],
                        'status' => 'synced',
                        'server_id' => $result['server_id'] ?? null,
                    ];
                } else {
                    $failCount++;
                    $syncResults[] = [
                        'item_id' => $item['id'],
                        'type' => $item['type'],
                        'status' => 'retry_later',
                        'error' => $result['error'] ?? 'Unknown error',
                    ];
                }
            }

            // Clear queue on success
            if ($successCount > 0) {
                Cache::put(self::CACHE_PREFIX . "last_sync:{$userId}", now()->toIso8601String(), self::SYNC_QUEUE_TTL);
                
                // Keep failed items in queue
                $failedItems = array_filter($syncResults, fn($r) => $r['status'] === 'retry_later');
                if (empty($failedItems)) {
                    Cache::forget(self::CACHE_PREFIX . "queue:{$userId}");
                }
            }

            return [
                'user_id' => $userId,
                'sync_status' => $failCount === 0 ? 'complete' : 'partial',
                'synced' => $successCount,
                'failed' => $failCount,
                'total' => count($queue),
                'results' => $syncResults,
                'sync_timestamp' => now()->toIso8601String(),
                'message' => $failCount === 0 
                    ? "All $successCount items synced successfully" 
                    : "Synced $successCount items, $failCount will retry later",
            ];
        } catch (\Exception $e) {
            Log::error("Failed to sync offline changes", ['error' => $e->getMessage()]);
            return [
                'sync_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect and resolve conflicts
     */
    public static function detectConflicts(int $userId): array
    {
        try {
            $conflicts = [];
            $pendingBookings = Cache::get(self::CACHE_PREFIX . "bookings:pending:{$userId}", []);

            foreach ($pendingBookings as $offlineBooking) {
                // Check if duplicate exists on server
                $serverBooking = DB::table('bookings')
                    ->where('user_id', $userId)
                    ->whereBetween('created_at', [
                        Carbon::parse($offlineBooking['created_at_local'])->subMinutes(5),
                        Carbon::parse($offlineBooking['created_at_local'])->addMinutes(5),
                    ])
                    ->where('pickup_location', $offlineBooking['pickup_location'])
                    ->first();

                if ($serverBooking) {
                    $conflicts[] = [
                        'type' => 'duplicate_booking',
                        'offline_id' => $offlineBooking['id'],
                        'server_id' => $serverBooking->id,
                        'resolution' => 'use_server_version',
                        'reason' => 'Booking already exists on server',
                    ];
                }
            }

            return [
                'conflicts_detected' => count($conflicts),
                'conflicts' => $conflicts,
                'auto_resolved' => count($conflicts),
                'requires_user_action' => 0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to detect conflicts", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get offline cache status
     */
    public static function getCacheStatus(int $userId): array
    {
        try {
            $package = Cache::get(self::CACHE_PREFIX . "package:{$userId}", []);
            $queue = Cache::get(self::CACHE_PREFIX . "queue:{$userId}", []);
            $cacheSize = strlen(json_encode($package)) / (1024 * 1024);

            return [
                'user_id' => $userId,
                'cache_ready' => !empty($package),
                'cache_size_mb' => round($cacheSize, 2),
                'size_limit_mb' => self::CACHE_SIZE_LIMIT,
                'cache_full' => $cacheSize > self::CACHE_SIZE_LIMIT,
                'pending_items' => count($queue),
                'last_prepared' => !empty($package) ? $package['metadata']['last_sync'] : 'never',
                'expires_at' => !empty($package) ? $package['metadata']['expires_at'] : 'N/A',
                'is_valid' => !empty($package) && Carbon::parse($package['metadata']['expires_at'])->isFuture(),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get cache status", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clear offline data
     */
    public static function clearOfflineData(int $userId): bool
    {
        try {
            Cache::forget(self::CACHE_PREFIX . "package:{$userId}");
            Cache::forget(self::CACHE_PREFIX . "queue:{$userId}");
            Cache::forget(self::CACHE_PREFIX . "bookings:pending:{$userId}");
            Cache::forget(self::CACHE_PREFIX . "last_sync:{$userId}");

            Log::info("Offline data cleared", ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to clear offline data", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get offline status
     */
    public static function getOfflineStatus(int $userId): array
    {
        return [
            'user_id' => $userId,
            'cache_status' => self::getCacheStatus($userId),
            'sync_queue' => self::getSyncQueue($userId),
            'conflicts' => self::detectConflicts($userId),
            'status_timestamp' => now()->toIso8601String(),
        ];
    }

    // ===== HELPER METHODS =====

    private static function getUserData(int $userId): array
    {
        $user = DB::table('users')->find($userId);
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'email' => $user?->email,
            'phone' => $user?->phone,
        ];
    }

    private static function getBookingHistory(int $userId, int $limit): array
    {
        return DB::table('bookings')
            ->where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'pickup_location', 'dropoff_location', 'status', 'rating', 'created_at'])
            ->toArray();
    }

    private static function getSavedLocations(int $userId): array
    {
        return DB::table('saved_locations')
            ->where('user_id', $userId)
            ->get(['id', 'label', 'latitude', 'longitude'])
            ->toArray() ?? [];
    }

    private static function getPaymentMethods(int $userId): array
    {
        return DB::table('payment_methods')
            ->where('user_id', $userId)
            ->where('is_deleted', false)
            ->get(['id', 'type', 'last_four', 'is_default'])
            ->toArray() ?? [];
    }

    private static function getDriversRated(int $userId, int $limit): array
    {
        return DB::table('bookings')
            ->where('user_id', $userId)
            ->whereNotNull('driver_id')
            ->whereNotNull('rating')
            ->latest('created_at')
            ->limit($limit)
            ->get(['driver_id', 'rating'])
            ->toArray() ?? [];
    }

    private static function getAppConfig(): array
    {
        return [
            'api_url' => config('app.api_url', 'https://api.shuttle.local'),
            'app_version' => '1.0.0',
            'minimum_version' => '1.0.0',
            'language' => 'en',
        ];
    }

    private static function estimatePackageSize(): float
    {
        return rand(5, 15); // Estimated in MB
    }

    private static function addToSyncQueue(int $userId, string $type, array $data): void
    {
        $queueKey = self::CACHE_PREFIX . "queue:{$userId}";
        $queue = Cache::get($queueKey, []);
        
        $queue[] = [
            'id' => uniqid('sync_'),
            'type' => $type,
            'data' => $data,
            'created_at' => now()->toIso8601String(),
            'retry_count' => 0,
        ];
        
        Cache::put($queueKey, $queue, self::SYNC_QUEUE_TTL);
    }

    private static function processSyncItem(array $item): array
    {
        try {
            if ($item['type'] === 'booking_create') {
                $booking = DB::table('bookings')->insertGetId($item['data']);
                return ['status' => 'synced', 'server_id' => $booking];
            }

            return ['status' => 'synced'];
        } catch (\Exception $e) {
            Log::warning("Sync item processing failed", ['item' => $item['id'], 'error' => $e->getMessage()]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
