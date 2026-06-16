<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * HTTP Caching Middleware
 * 
 * Implements HTTP caching headers:
 * - ETag generation & validation
 * - Last-Modified headers
 * - Cache-Control headers
 * - 304 Not Modified responses
 * - Conditional request handling
 */
class HttpCacheMiddleware
{
    /**
     * List of routes that should be cached
     */
    private const CACHEABLE_ROUTES = [
        'GET /api/bookings/*',
        'GET /api/drivers/*',
        'GET /api/vehicles/*',
        'GET /api/schedules/*',
        'GET /admin/dashboard/*',
        'GET /admin/reports/*',
    ];

    /**
     * Cache TTL by route pattern (seconds)
     */
    private const CACHE_TTLS = [
        'dashboard' => 300,    // 5 minutes
        'reports' => 3600,     // 1 hour
        'bookings' => 60,      // 1 minute (frequent updates)
        'drivers' => 300,      // 5 minutes
        'vehicles' => 3600,    // 1 hour (rarely changes)
        'schedules' => 1800,   // 30 minutes
    ];

    /**
     * Handle the request
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip caching for non-GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Skip caching for authenticated requests with user data
        if ($request->user() && $this->containsUserSpecificData($request)) {
            return $next($request);
        }

        // Get the response
        $response = $next($request);

        // Only cache successful responses
        if ($response->status() !== 200) {
            return $response;
        }

        // Add caching headers to response
        return $this->addCacheHeaders($request, $response);
    }

    /**
     * Add HTTP caching headers to response
     */
    private function addCacheHeaders(Request $request, Response $response): Response
    {
        // Generate ETag
        $eTag = $this->generateETag($response);
        $response->header('ETag', '"' . $eTag . '"');

        // Check If-None-Match header
        if ($request->header('If-None-Match') === '"' . $eTag . '"') {
            return $this->notModifiedResponse($response);
        }

        // Add Last-Modified header
        $lastModified = $this->getLastModified($response);
        if ($lastModified) {
            $response->header('Last-Modified', $lastModified);

            // Check If-Modified-Since header
            if ($this->isNotModifiedSince($request, $lastModified)) {
                return $this->notModifiedResponse($response);
            }
        }

        // Add Cache-Control headers based on route
        $this->addCacheControlHeaders($request, $response);

        // Add Vary header to indicate what headers affect caching
        $response->header('Vary', 'Accept-Encoding, Authorization');

        return $response;
    }

    /**
     * Generate ETag from response content
     */
    private function generateETag(Response $response): string
    {
        $content = $response->getContent();
        return md5($content);
    }

    /**
     * Get Last-Modified timestamp
     */
    private function getLastModified(Response $response): ?string
    {
        // Try to get from response headers
        if ($response->header('Last-Modified')) {
            return $response->header('Last-Modified');
        }

        // Get current time in HTTP format
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        return $dateTime->format('D, d M Y H:i:s T');
    }

    /**
     * Check if content not modified since given time
     */
    private function isNotModifiedSince(Request $request, string $lastModified): bool
    {
        $ifModifiedSince = $request->header('If-Modified-Since');
        if (!$ifModifiedSince) {
            return false;
        }

        try {
            $ifTime = new \DateTime($ifModifiedSince);
            $lastTime = new \DateTime($lastModified);
            return $ifTime >= $lastTime;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Return 304 Not Modified response
     */
    private function notModifiedResponse(Response $response): Response
    {
        $response->setStatusCode(304);
        $response->setContent(null);
        
        // Remove body-related headers for 304 responses
        $response->header('Content-Length', 0);
        
        return $response;
    }

    /**
     * Add Cache-Control headers based on route
     */
    private function addCacheControlHeaders(Request $request, Response $response): void
    {
        $ttl = $this->getTTLForRoute($request->getPathInfo());
        
        if ($ttl > 0) {
            // Cache in browser and intermediaries
            $response->header('Cache-Control', "public, max-age={$ttl}");
        } else {
            // Don't cache
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        // Add Expires header for older clients
        $expiresTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $expiresTime->add(new \DateInterval('PT' . $ttl . 'S'));
        $response->header('Expires', $expiresTime->format('D, d M Y H:i:s T'));
    }

    /**
     * Get TTL for specific route
     */
    private function getTTLForRoute(string $path): int
    {
        foreach (self::CACHE_TTLS as $pattern => $ttl) {
            if (stripos($path, $pattern) !== false) {
                return $ttl;
            }
        }

        // Default: 1 hour for unknown routes
        return 3600;
    }

    /**
     * Check if request contains user-specific data
     */
    private function containsUserSpecificData(Request $request): bool
    {
        $path = $request->getPathInfo();
        
        return stripos($path, '/profile') !== false ||
               stripos($path, '/bookings') !== false ||
               stripos($path, '/preferences') !== false ||
               stripos($path, '/payment') !== false;
    }

    /**
     * Check if route is cacheable
     */
    private function isCacheableRoute(string $path): bool
    {
        foreach (self::CACHEABLE_ROUTES as $pattern) {
            if ($this->routeMatches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match route pattern
     */
    private function routeMatches(string $pattern, string $path): bool
    {
        $pattern = str_replace('/*', '.*', preg_quote($pattern));
        return (bool) preg_match("^{$pattern}$", $path);
    }
}
