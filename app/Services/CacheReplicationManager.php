<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Cache Replication Manager
 * 
 * Manages distributed cache across multiple servers:
 * - Multi-server cache synchronization
 * - Replication strategy
 * - Failover handling
 * - Sync metrics & monitoring
 */
class CacheReplicationManager
{
    private array $servers = [];
    private int $syncInterval = 300; // 5 minutes
    private array $metrics = [
        'syncs_completed' => 0,
        'syncs_failed' => 0,
        'last_sync' => null,
        'replication_lag' => 0,
    ];

    /**
     * Initialize cache replication
     */
    public function __construct(array $serverConfigs = [])
    {
        $this->servers = $serverConfigs ?: $this->getServersFromConfig();
        Log::info("Cache replication initialized", ['servers' => count($this->servers)]);
    }

    /**
     * Get server configurations from Laravel config
     */
    private function getServersFromConfig(): array
    {
        return config('cache.servers', [
            [
                'name' => 'primary',
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_DB', 0),
            ],
            // Add secondary servers as needed
        ]);
    }

    /**
     * Synchronize cache across all servers
     */
    public function syncAll(): array
    {
        Log::info("Starting cache synchronization across all servers");
        $startTime = microtime(true);

        try {
            // Get keys from primary server
            $primaryKeys = $this->getKeysFromServer($this->servers[0]);

            // Sync to all other servers
            $syncResults = [];
            for ($i = 1; $i < count($this->servers); $i++) {
                $syncResults[$this->servers[$i]['name']] = $this->syncServerData(
                    $this->servers[0],
                    $this->servers[$i],
                    $primaryKeys
                );
            }

            $this->metrics['syncs_completed']++;
            $this->metrics['last_sync'] = now();
            $this->metrics['replication_lag'] = (microtime(true) - $startTime) * 1000;

            Log::info("Cache synchronization completed", [
                'duration_ms' => $this->metrics['replication_lag'],
                'results' => $syncResults,
            ]);

            return [
                'status' => 'success',
                'servers_synced' => count($syncResults),
                'duration_ms' => $this->metrics['replication_lag'],
                'results' => $syncResults,
            ];
        } catch (\Exception $e) {
            $this->metrics['syncs_failed']++;
            Log::error("Cache synchronization failed", ['error' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync data from source server to target server
     */
    private function syncServerData(array $source, array $target, array $keys): array
    {
        $synced = 0;
        $failed = 0;

        try {
            $sourceClient = $this->getRedisConnection($source);
            $targetClient = $this->getRedisConnection($target);

            foreach ($keys as $key) {
                try {
                    // Get value from source
                    $value = $sourceClient->get($key);
                    $ttl = $sourceClient->ttl($key);

                    // Set on target
                    if ($ttl > 0) {
                        $targetClient->setex($key, $ttl, $value);
                    } else {
                        $targetClient->set($key, $value);
                    }

                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("Failed to sync key", ['key' => $key, 'error' => $e->getMessage()]);
                }
            }

            return [
                'server' => $target['name'],
                'synced' => $synced,
                'failed' => $failed,
                'total' => count($keys),
            ];
        } catch (\Exception $e) {
            return [
                'server' => $target['name'],
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all keys from a server
     */
    private function getKeysFromServer(array $server): array
    {
        try {
            $client = $this->getRedisConnection($server);
            return $client->keys('*');
        } catch (\Exception $e) {
            Log::error("Failed to get keys from server", ['server' => $server['name'], 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get Redis connection for a server
     */
    private function getRedisConnection(array $server)
    {
        return Redis::connection($server['name'] ?? 'default');
    }

    /**
     * Check health of all cache servers
     */
    public function checkHealth(): array
    {
        $health = [];

        foreach ($this->servers as $server) {
            try {
                $client = $this->getRedisConnection($server);
                $ping = $client->ping();

                $info = $client->info();
                $health[$server['name']] = [
                    'status' => 'healthy',
                    'ping' => $ping,
                    'memory_used' => $info['used_memory_human'] ?? 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
                ];
            } catch (\Exception $e) {
                $health[$server['name']] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $health;
    }

    /**
     * Handle failover to secondary server
     */
    public function failover(): bool
    {
        Log::warning("Initiating cache failover");

        try {
            // Check primary health
            $primaryHealth = $this->checkHealth()[$this->servers[0]['name']] ?? null;

            if ($primaryHealth['status'] !== 'healthy') {
                // Switch to secondary
                Log::warning("Primary server unhealthy, switching to secondary");

                // Rotate servers
                $secondary = array_splice($this->servers, 1, 1)[0];
                array_unshift($this->servers, $secondary);

                // Sync to restore primary
                $this->syncAll();

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Failover failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Monitor replication lag
     */
    public function getReplicationLag(): float
    {
        return $this->metrics['replication_lag'];
    }

    /**
     * Get replication metrics
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'servers' => count($this->servers),
            'health' => $this->checkHealth(),
        ]);
    }

    /**
     * Flush all cache on all servers
     */
    public function flushAll(): bool
    {
        try {
            foreach ($this->servers as $server) {
                $client = $this->getRedisConnection($server);
                $client->flushDb();
            }

            Log::info("All caches flushed across all servers");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to flush all caches", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache size on all servers
     */
    public function getCacheSize(): array
    {
        $sizes = [];

        foreach ($this->servers as $server) {
            try {
                $client = $this->getRedisConnection($server);
                $info = $client->info();

                $sizes[$server['name']] = [
                    'used_memory' => $info['used_memory'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                    'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                    'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'N/A',
                ];
            } catch (\Exception $e) {
                $sizes[$server['name']] = ['error' => $e->getMessage()];
            }
        }

        return $sizes;
    }

    /**
     * Schedule periodic cache synchronization
     */
    public function schedulePeriodicSync(): void
    {
        \Artisan::command('cache:sync', function () {
            $result = $this->syncAll();
            $this->info('Cache synchronization: ' . json_encode($result));
        })->everyFiveMinutes();

        Log::info("Periodic cache synchronization scheduled");
    }

    /**
     * Get detailed replication status
     */
    public function getReplicationStatus(): array
    {
        return [
            'servers' => $this->servers,
            'metrics' => $this->metrics,
            'health' => $this->checkHealth(),
            'sizes' => $this->getCacheSize(),
        ];
    }
}
