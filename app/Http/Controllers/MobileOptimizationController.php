<?php

namespace App\Http\Controllers;

use App\Services\OfflineModeService;
use App\Services\PWAService;
use App\Services\AdvancedSyncService;
use App\Services\MobilePerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Mobile API Controller
 * 
 * Endpoints for mobile optimization features:
 * - Offline sync
 * - PWA management
 * - Advanced sync protocol
 * - Performance monitoring
 */
class MobileOptimizationController extends Controller
{
    /**
     * Get offline package
     * GET /api/mobile/offline-package
     */
    public function getOfflinePackage(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $package = OfflineModeService::prepareOfflinePackage($userId);

            return response()->json([
                'status' => 'success',
                'data' => $package,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get offline package", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to prepare offline package'], 500);
        }
    }

    /**
     * Create offline booking
     * POST /api/mobile/offline-booking
     */
    public function createOfflineBooking(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'pickup_location' => 'required|array',
                'dropoff_location' => 'required|array',
                'ride_type' => 'required|string',
                'scheduled_at' => 'nullable|date',
            ]);

            $booking = OfflineModeService::createOfflineBooking($userId, $validated);

            return response()->json([
                'status' => 'success',
                'booking' => $booking,
                'sync_when_online' => true,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Failed to create offline booking", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create booking'], 500);
        }
    }

    /**
     * Sync offline changes
     * POST /api/mobile/sync-offline
     */
    public function syncOfflineChanges(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'bookings' => 'nullable|array',
                'status_updates' => 'nullable|array',
                'ratings' => 'nullable|array',
            ]);

            $result = OfflineModeService::syncOfflineChanges($userId, $validated);

            return response()->json([
                'status' => 'success',
                'sync_result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync offline changes", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to sync'], 500);
        }
    }

    /**
     * Smart sync endpoint
     * POST /api/mobile/smart-sync
     */
    public function smartSync(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $lastSyncToken = $request->input('sync_token');
            $result = AdvancedSyncService::smartSync($userId, $lastSyncToken);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Smart sync failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Sync failed'], 500);
        }
    }

    /**
     * Resolve conflicts
     * POST /api/mobile/resolve-conflict
     */
    public function resolveConflict(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'conflict_id' => 'required|string',
                'type' => 'required|string',
                'client_data' => 'required|array',
                'server_data' => 'required|array',
            ]);

            $resolution = AdvancedSyncService::resolveConflict($validated);

            return response()->json([
                'status' => 'success',
                'resolution' => $resolution,
            ]);
        } catch (\Exception $e) {
            Log::error("Conflict resolution failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to resolve conflict'], 500);
        }
    }

    /**
     * Detect conflicts
     * POST /api/mobile/detect-conflicts
     */
    public function detectConflicts(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $clientData = $request->validate([
                'bookings' => 'nullable|array',
                'status_updates' => 'nullable|array',
            ]);

            $result = AdvancedSyncService::detectConflicts($userId, $clientData);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Conflict detection failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Conflict detection failed'], 500);
        }
    }

    /**
     * Batch requests
     * POST /api/mobile/batch
     */
    public function batchRequests(Request $request): JsonResponse
    {
        try {
            $requests = $request->validate([
                'requests' => 'required|array',
            ]);

            $result = MobilePerformanceService::batchRequests($requests['requests']);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Batch request failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Batch request failed'], 500);
        }
    }

    /**
     * Get sync status
     * GET /api/mobile/sync-status
     */
    public function getSyncStatus(): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $status = AdvancedSyncService::getSyncStatus($userId);

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get sync status", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to get status'], 500);
        }
    }

    /**
     * Configure sync settings
     * POST /api/mobile/sync-settings
     */
    public function configureSyncSettings(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $settings = $request->validate([
                'auto_sync_enabled' => 'boolean',
                'auto_sync_interval' => 'integer|min:1|max:60',
                'wifi_only_sync' => 'boolean',
                'background_sync' => 'boolean',
                'compression_enabled' => 'boolean',
                'delta_sync_enabled' => 'boolean',
                'batch_size' => 'integer|min:10|max:500',
                'conflict_strategy' => 'string|in:last_write_wins,server_wins,client_wins,merge',
            ]);

            $result = AdvancedSyncService::configureSyncSettings($userId, $settings);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to configure sync settings", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Configuration failed'], 500);
        }
    }

    /**
     * Get PWA manifest
     * GET /manifest.json
     */
    public function getManifest(): JsonResponse
    {
        return response()->json(PWAService::getWebManifest(), 200, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get PWA status
     * GET /api/mobile/pwa-status
     */
    public function getPWAStatus(): JsonResponse
    {
        try {
            $status = PWAService::getPWAStatus();

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get PWA status", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to get PWA status'], 500);
        }
    }

    /**
     * Register PWA installation
     * POST /api/mobile/pwa-install
     */
    public function registerPWAInstallation(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $deviceId = $request->input('device_id', uniqid('device_'));
            $installed = PWAService::registerInstallation($userId, $deviceId);

            return response()->json([
                'status' => 'success',
                'installed' => $installed,
                'device_id' => $deviceId,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to register PWA installation", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Registration failed'], 500);
        }
    }

    /**
     * Get performance recommendations
     * GET /api/mobile/performance/recommendations
     */
    public function getPerformanceRecommendations(): JsonResponse
    {
        try {
            $recommendations = MobilePerformanceService::getPerformanceRecommendations();

            return response()->json([
                'status' => 'success',
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get performance recommendations", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to get recommendations'], 500);
        }
    }

    /**
     * Get image optimization config
     * GET /api/mobile/performance/images
     */
    public function getImageOptimization(): JsonResponse
    {
        try {
            $config = MobilePerformanceService::getImageOptimization();

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get image optimization config", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to get config'], 500);
        }
    }

    /**
     * Analyze performance bottlenecks
     * GET /api/mobile/performance/bottlenecks
     */
    public function analyzeBottlenecks(): JsonResponse
    {
        try {
            $analysis = MobilePerformanceService::analyzeBottlenecks();

            return response()->json([
                'status' => 'success',
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to analyze bottlenecks", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Analysis failed'], 500);
        }
    }

    /**
     * Get bandwidth stats
     * GET /api/mobile/sync/bandwidth
     */
    public function getBandwidthStats(): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $stats = AdvancedSyncService::getBandwidthStats($userId);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get bandwidth stats", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to get stats'], 500);
        }
    }

    /**
     * Get service worker script
     * GET /service-worker.js
     */
    public function getServiceWorker(): JsonResponse
    {
        try {
            $script = PWAService::getServiceWorkerScript();
            return response($script, 200)
                ->header('Content-Type', 'application/javascript')
                ->header('Service-Worker-Allowed', '/');
        } catch (\Exception $e) {
            Log::error("Failed to get service worker", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Service worker not available'], 500);
        }
    }
}
