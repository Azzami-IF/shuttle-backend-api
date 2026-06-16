<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Query-Level Caching Service
 * 
 * Provides Eloquent query caching with:
 * - Tag-based invalidation
 * - Automatic cache key generation
 * - TTL management
 * - Hit/miss metrics
 * - Cache warming support
 */
class QueryCacheService
{
    // Default cache TTLs (in seconds)
    const TTL_SHORT = 300;      // 5 minutes
    const TTL_MEDIUM = 3600;    // 1 hour
    const TTL_LONG = 86400;     // 24 hours
    const TTL_PERMANENT = 604800; // 7 days

    // Cache key prefixes by entity type
    private const CACHE_PREFIX = 'query_cache:';
    
    private array $tags = [];
    private int $ttl = self::TTL_MEDIUM;
    private bool $forceRefresh = false;
    private array $metrics = [];

    /**
     * Create a cacheable query from a model
     */
    public static function query(string $modelClass): self
    {
        return new self($modelClass);
    }

    /**
     * Cache a query builder result
     */
    public function cacheBuilder(Builder $builder): self
    {
        $this->builder = $builder;
        return $this;
    }

    /**
     * Add cache tags for invalidation
     */
    public function tags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }

    /**
     * Set cache TTL in seconds
     */
    public function ttl(int $seconds): self
    {
        $this->ttl = $seconds;
        return $this;
    }

    /**
     * Force refresh (bypass cache)
     */
    public function fresh(): self
    {
        $this->forceRefresh = true;
        return $this;
    }

    /**
     * Get cached results or execute query
     */
    public function get()
    {
        $cacheKey = $this->generateKey();
        
        // Check if force refresh requested
        if ($this->forceRefresh) {
            $this->invalidateCache($cacheKey);
            return $this->executeAndCache($cacheKey);
        }

        // Try to get from cache
        if (Cache::has($cacheKey)) {
            $this->recordHit($cacheKey);
            return Cache::get($cacheKey);
        }

        // Cache miss - execute and store
        $this->recordMiss($cacheKey);
        return $this->executeAndCache($cacheKey);
    }

    /**
     * Get first result from cache or query
     */
    public function first()
    {
        $results = $this->get();
        return is_array($results) ? reset($results) : null;
    }

    /**
     * Get paginated results with caching
     */
    public function paginate(int $perPage = 15)
    {
        // Pagination requires query execution (can't be fully cached)
        // But we can cache the count
        $countKey = $this->generateKey() . ':count';
        
        if (!Cache::has($countKey) || $this->forceRefresh) {
            Cache::tags($this->tags)->put(
                $countKey,
                $this->builder->count(),
                $this->ttl
            );
        }

        return $this->builder->paginate($perPage);
    }

    /**
     * Execute query and cache result
     */
    private function executeAndCache(string $cacheKey)
    {
        $startTime = microtime(true);
        
        // Execute the query
        $results = $this->builder->get();
        
        $executionTime = (microtime(true) - $startTime) * 1000;

        // Store in cache with tags
        if (empty($this->tags)) {
            Cache::put($cacheKey, $results, $this->ttl);
        } else {
            Cache::tags($this->tags)->put($cacheKey, $results, $this->ttl);
        }

        Log::debug("Query executed and cached", [
            'key' => $cacheKey,
            'count' => count($results),
            'time_ms' => $executionTime,
            'tags' => $this->tags,
        ]);

        return $results;
    }

    /**
     * Generate unique cache key from query
     */
    private function generateKey(): string
    {
        $sql = $this->builder->toSql();
        $bindings = $this->builder->getBindings();
        $hash = md5($sql . serialize($bindings));
        
        return self::CACHE_PREFIX . $hash;
    }

    /**
     * Invalidate cached query by key
     */
    public static function invalidate(string $cacheKey): void
    {
        Cache::forget($cacheKey);
        Log::debug("Cache invalidated", ['key' => $cacheKey]);
    }

    /**
     * Invalidate by tags
     */
    public static function invalidateByTags(array $tags): void
    {
        Cache::tags($tags)->flush();
        Log::debug("Cache flushed by tags", ['tags' => $tags]);
    }

    /**
     * Invalidate all query cache
     */
    public static function invalidateAll(): void
    {
        Cache::flush();
        Log::info("All query cache invalidated");
    }

    /**
     * Record cache hit for metrics
     */
    private function recordHit(string $key): void
    {
        $metricsKey = 'cache_metrics:hits';
        $current = Cache::get($metricsKey, 0);
        Cache::put($metricsKey, $current + 1, 86400);
    }

    /**
     * Record cache miss for metrics
     */
    private function recordMiss(string $key): void
    {
        $metricsKey = 'cache_metrics:misses';
        $current = Cache::get($metricsKey, 0);
        Cache::put($metricsKey, $current + 1, 86400);
    }

    /**
     * Get cache hit rate statistics
     */
    public static function getMetrics(): array
    {
        $hits = Cache::get('cache_metrics:hits', 0);
        $misses = Cache::get('cache_metrics:misses', 0);
        $total = $hits + $misses;
        $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'total' => $total,
            'hit_rate' => round($hitRate, 2) . '%',
            'memory_usage' => Cache::getStore()->connection()->info()['memory'],
        ];
    }

    /**
     * Warm cache for frequently accessed queries
     */
    public static function warmCache(array $queries): void
    {
        Log::info("Starting cache warming", ['count' => count($queries)]);

        foreach ($queries as $query) {
            try {
                $query->get();
            } catch (\Exception $e) {
                Log::error("Cache warming failed", ['error' => $e->getMessage()]);
            }
        }

        Log::info("Cache warming completed");
    }

    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        $redisInfo = Cache::getStore()->connection()->info();
        
        return [
            'used_memory' => $redisInfo['used_memory_human'] ?? 'N/A',
            'used_memory_peak' => $redisInfo['used_memory_peak_human'] ?? 'N/A',
            'evicted_keys' => $redisInfo['evicted_keys'] ?? 0,
            'keyspace_hits' => $redisInfo['keyspace_hits'] ?? 0,
            'keyspace_misses' => $redisInfo['keyspace_misses'] ?? 0,
        ];
    }
}
