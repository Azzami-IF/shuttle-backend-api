<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket/Broadcasting Configuration & Helpers
 * 
 * Configures and manages WebSocket connections:
 * - Connection pooling
 * - Channel management
 * - Presence tracking
 * - Performance monitoring
 */
class WebSocketManager
{
    private const REDIS_PREFIX = 'websocket:';
    private const CONN_TIMEOUT = 3600; // 1 hour

    /**
     * Initialize WebSocket configuration
     */
    public static function configure(): array
    {
        return [
            'driver' => env('BROADCAST_DRIVER', 'redis'),
            'redis' => [
                'client' => 'default',
                'connection' => 'default',
            ],
            'servers' => [
                [
                    'host' => env('WEBSOCKET_HOST', 'localhost'),
                    'port' => env('WEBSOCKET_PORT', 6001),
                    'key' => env('WEBSOCKET_KEY', 'your-key'),
                    'secret' => env('WEBSOCKET_SECRET', 'your-secret'),
                ],
            ],
            'options' => [
                'encrypted' => env('WEBSOCKET_ENCRYPTED', false),
                'auto_ssl' => env('WEBSOCKET_AUTO_SSL', false),
                'cluster' => [
                    'enabled' => env('WEBSOCKET_CLUSTER', false),
                ],
                'limitations' => [
                    'max_connections' => 10000,
                    'max_channels_per_user' => 50,
                    'max_message_size' => 102400, // 100KB
                ],
            ],
        ];
    }

    /**
     * Track user connection
     */
    public static function trackConnection(int $userId, string $connectionId, string $channel = null): bool
    {
        try {
            $key = self::REDIS_PREFIX . "user:{$userId}:connections";
            Redis::setex($key, self::CONN_TIMEOUT, $connectionId);

            if ($channel) {
                $channelKey = self::REDIS_PREFIX . "channel:{$channel}:users";
                Redis::sadd($channelKey, $userId);
                Redis::expire($channelKey, self::CONN_TIMEOUT);
            }

            Log::debug("Connection tracked", ['user_id' => $userId, 'connection_id' => $connectionId]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to track connection", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Remove connection tracking
     */
    public static function untrackConnection(int $userId, string $connectionId): bool
    {
        try {
            $key = self::REDIS_PREFIX . "user:{$userId}:connections";
            Redis::del($key);

            Log::debug("Connection untracked", ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to untrack connection", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get active connections for user
     */
    public static function getActiveConnections(int $userId): array
    {
        try {
            $pattern = self::REDIS_PREFIX . "user:{$userId}:*";
            $keys = Redis::keys($pattern);
            return array_map(function ($key) {
                return Redis::get($key);
            }, $keys);
        } catch (\Exception $e) {
            Log::error("Failed to get active connections", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get users in channel
     */
    public static function getUsersInChannel(string $channel): array
    {
        try {
            $key = self::REDIS_PREFIX . "channel:{$channel}:users";
            return (array) Redis::smembers($key);
        } catch (\Exception $e) {
            Log::error("Failed to get users in channel", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get connection statistics
     */
    public static function getStats(): array
    {
        try {
            $pattern = self::REDIS_PREFIX . "user:*:connections";
            $userKeys = Redis::keys($pattern);
            $totalConnections = count($userKeys);

            $channelPattern = self::REDIS_PREFIX . "channel:*:users";
            $channelKeys = Redis::keys($channelPattern);

            return [
                'active_connections' => $totalConnections,
                'active_channels' => count($channelKeys),
                'memory_usage' => Redis::info('memory')['used_memory_human'] ?? 'N/A',
                'uptime_seconds' => Redis::info('server')['uptime_in_seconds'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get WebSocket stats", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Broadcast to channel
     */
    public static function broadcastToChannel(string $channel, string $event, array $data): bool
    {
        try {
            $message = [
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
            ];

            Redis::publish($channel, json_encode($message));
            Log::debug("Message broadcast to channel", ['channel' => $channel, 'event' => $event]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to broadcast to channel", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast to user
     */
    public static function broadcastToUser(int $userId, string $event, array $data): bool
    {
        $channel = "private-user.$userId";
        return self::broadcastToChannel($channel, $event, $data);
    }

    /**
     * Check if user is online
     */
    public static function isUserOnline(int $userId): bool
    {
        try {
            $pattern = self::REDIS_PREFIX . "user:{$userId}:*";
            return count(Redis::keys($pattern)) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get online users count
     */
    public static function getOnlineUsersCount(): int
    {
        try {
            $pattern = self::REDIS_PREFIX . "user:*:connections";
            return count(Redis::keys($pattern));
        } catch (\Exception $e) {
            Log::error("Failed to get online users count", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Set channel permissions
     */
    public static function setChannelPermissions(string $channel, array $permissions): bool
    {
        try {
            $key = self::REDIS_PREFIX . "channel:{$channel}:permissions";
            Redis::setex($key, self::CONN_TIMEOUT, json_encode($permissions));
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to set channel permissions", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get channel permissions
     */
    public static function getChannelPermissions(string $channel): array
    {
        try {
            $key = self::REDIS_PREFIX . "channel:{$channel}:permissions";
            $permissions = Redis::get($key);
            return $permissions ? json_decode($permissions, true) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Rate limit WebSocket messages
     */
    public static function rateLimit(int $userId, int $maxMessagesPerMinute = 60): bool
    {
        try {
            $key = self::REDIS_PREFIX . "ratelimit:user:{$userId}";
            $current = (int) Redis::get($key) ?? 0;

            if ($current >= $maxMessagesPerMinute) {
                return false; // Rate limited
            }

            Redis::incr($key);
            Redis::expire($key, 60);
            return true;
        } catch (\Exception $e) {
            Log::error("Rate limit check failed", ['error' => $e->getMessage()]);
            return true; // Allow on error
        }
    }

    /**
     * Clean up stale connections
     */
    public static function cleanupStaleConnections(): int
    {
        try {
            $pattern = self::REDIS_PREFIX . "user:*:connections";
            $keys = Redis::keys($pattern);

            $cleaned = 0;
            foreach ($keys as $key) {
                $ttl = Redis::ttl($key);
                if ($ttl < 0) {
                    Redis::del($key);
                    $cleaned++;
                }
            }

            Log::info("Cleaned up stale WebSocket connections", ['count' => $cleaned]);
            return $cleaned;
        } catch (\Exception $e) {
            Log::error("Failed to cleanup stale connections", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Health check for WebSocket server
     */
    public static function healthCheck(): array
    {
        try {
            $ping = Redis::ping();
            $stats = self::getStats();

            return [
                'status' => $ping === 'PONG' ? 'healthy' : 'unhealthy',
                'redis_connection' => $ping === 'PONG' ? 'connected' : 'disconnected',
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }
}
