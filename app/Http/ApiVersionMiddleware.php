<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Versioning Middleware
 * 
 * Handles API version detection and routing:
 * - Version detection from Accept header
 * - Request transformation for backwards compatibility
 * - Response formatting per version
 * - Deprecation warnings
 */
class ApiVersionMiddleware
{
    /**
     * Supported API versions
     */
    private const SUPPORTED_VERSIONS = [
        '1.0' => ['status' => 'current', 'sunset' => null],
        '1.1' => ['status' => 'deprecated', 'sunset' => '2026-12-31'],
        '2.0' => ['status' => 'deprecated', 'sunset' => '2026-12-31'],
    ];

    private const DEFAULT_VERSION = '1.0';

    /**
     * Handle the request
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract API version from request
        $version = $this->getApiVersion($request);
        $request->attributes->set('api_version', $version);

        // Transform request if needed for backwards compatibility
        if ($version !== '1.0') {
            $this->transformRequest($request, $version);
        }

        // Get the response
        $response = $next($request);

        // Transform response if needed
        if ($response instanceof JsonResponse && $version !== '1.0') {
            $this->transformResponse($response, $version);
        }

        // Add version and deprecation headers
        $this->addVersionHeaders($response, $version);

        return $response;
    }

    /**
     * Extract API version from request
     */
    private function getApiVersion(Request $request): string
    {
        // Check Accept header for version
        // Format: application/vnd.shuttle.v1.0+json
        $acceptHeader = $request->header('Accept', '');

        if (preg_match('/v(\d+\.\d+)/', $acceptHeader, $matches)) {
            $version = $matches[1];
            return $this->isValidVersion($version) ? $version : self::DEFAULT_VERSION;
        }

        // Check X-API-Version header
        $headerVersion = $request->header('X-API-Version');
        if ($headerVersion && $this->isValidVersion($headerVersion)) {
            return $headerVersion;
        }

        // Check query parameter
        $queryVersion = $request->query('api_version');
        if ($queryVersion && $this->isValidVersion($queryVersion)) {
            return $queryVersion;
        }

        return self::DEFAULT_VERSION;
    }

    /**
     * Check if version is valid and supported
     */
    private function isValidVersion(string $version): bool
    {
        return isset(self::SUPPORTED_VERSIONS[$version]);
    }

    /**
     * Transform incoming request for backwards compatibility
     */
    private function transformRequest(Request $request, string $version): void
    {
        match ($version) {
            '1.1' => $this->transformRequestFrom1_1($request),
            '2.0' => $this->transformRequestFrom2_0($request),
            default => null,
        };
    }

    /**
     * Transform request from v1.1 to v1.0
     */
    private function transformRequestFrom1_1(Request $request): void
    {
        // v1.1 used 'vehicle_id' but v1.0 uses 'car_id'
        if ($request->has('vehicle_id') && !$request->has('car_id')) {
            $request->merge(['car_id' => $request->input('vehicle_id')]);
        }

        // v1.1 nested driver info, v1.0 has flat structure
        if ($request->has('driver') && is_array($request->input('driver'))) {
            $driverData = $request->input('driver');
            $request->merge($driverData);
        }
    }

    /**
     * Transform request from v2.0 to v1.0
     */
    private function transformRequestFrom2_0(Request $request): void
    {
        // v2.0 uses newer field names, need to map back to v1.0
        $mapping = [
            'passenger_id' => 'user_id',
            'origin_location' => 'origin',
            'destination_location' => 'destination',
            'scheduled_at' => 'booking_time',
        ];

        foreach ($mapping as $newField => $oldField) {
            if ($request->has($newField) && !$request->has($oldField)) {
                $request->merge([$oldField => $request->input($newField)]);
            }
        }
    }

    /**
     * Transform response for backwards compatibility
     */
    private function transformResponse(JsonResponse $response, string $version): void
    {
        $data = json_decode($response->getContent(), true);

        $transformedData = match ($version) {
            '1.1' => $this->transformResponseTo1_1($data),
            '2.0' => $this->transformResponseTo2_0($data),
            default => $data,
        };

        $response->setData($transformedData);
    }

    /**
     * Transform response to v1.1 format
     */
    private function transformResponseTo1_1(array $data): array
    {
        // Add wrapper for v1.1 compatibility
        if (isset($data['data'])) {
            $data['result'] = $data['data'];
            unset($data['data']);
        }

        // Transform vehicle_id back to vehicle_id in nested structure
        if (isset($data['result']) && is_array($data['result'])) {
            $data['result'] = array_map(function ($item) {
                if (isset($item['car_id'])) {
                    $item['vehicle_id'] = $item['car_id'];
                }
                return $item;
            }, $data['result']);
        }

        return $data;
    }

    /**
     * Transform response to v2.0 format
     */
    private function transformResponseTo2_0(array $data): array
    {
        // v2.0 uses different field names
        $mapping = [
            'user_id' => 'passenger_id',
            'origin' => 'origin_location',
            'destination' => 'destination_location',
            'booking_time' => 'scheduled_at',
        ];

        return array_map(function ($item) use ($mapping) {
            if (!is_array($item)) {
                return $item;
            }

            foreach ($mapping as $oldField => $newField) {
                if (isset($item[$oldField])) {
                    $item[$newField] = $item[$oldField];
                    unset($item[$oldField]);
                }
            }

            return $item;
        }, $data);
    }

    /**
     * Add version headers to response
     */
    private function addVersionHeaders(JsonResponse $response, string $version): void
    {
        $versionInfo = self::SUPPORTED_VERSIONS[$version];

        // Add version header
        $response->header('X-API-Version', $version);
        $response->header('X-API-Version-Status', $versionInfo['status']);

        // Add deprecation warning if deprecated
        if ($versionInfo['status'] === 'deprecated') {
            $response->header('Deprecation', 'true');
            $response->header('Sunset', $versionInfo['sunset']);
            $response->header('Link', '<https://docs.shuttle.com/api/migration>; rel="deprecation"');
        }

        // Add version in response body
        $data = json_decode($response->getContent(), true);
        $data['meta'] = $data['meta'] ?? [];
        $data['meta']['version'] = $version;
        $data['meta']['version_status'] = $versionInfo['status'];

        if ($versionInfo['status'] === 'deprecated') {
            $data['meta']['deprecation_date'] = $versionInfo['sunset'];
            $data['meta']['migration_guide'] = 'https://docs.shuttle.com/api/migration';
        }

        $response->setData($data);
    }
}
