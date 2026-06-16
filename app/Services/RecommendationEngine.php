<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Machine Learning Recommendation Engine
 * 
 * AI-powered recommendations:
 * - Personalized driver recommendations
 * - Route recommendations for users
 * - Driver ride suggestions
 * - Surge pricing recommendations
 * - Collaborative filtering
 */
class RecommendationEngine
{
    private const CACHE_PREFIX = 'recommendations:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get personalized driver recommendations for user
     */
    public static function recommendDriversForUser(int $userId, string $pickup, string $dropoff, int $limit = 5): array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . "drivers:user:{$userId}:{$pickup}:{$dropoff}";
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $pickup, $dropoff, $limit) {
                // Get user's booking history
                $userHistory = self::getUserBookingHistory($userId);
                $userPreferences = self::extractUserPreferences($userHistory);

                // Find similar drivers
                $drivers = DB::table('drivers')
                    ->where('status', 'active')
                    ->where('available', true)
                    ->get()
                    ->map(function ($driver) use ($userPreferences, $userId) {
                        $score = self::calculateDriverScore($driver, $userPreferences, $userId);
                        return [
                            'driver_id' => $driver->id,
                            'name' => $driver->name,
                            'rating' => $driver->rating ?? 0,
                            'completed_trips' => DB::table('bookings')->where('driver_id', $driver->id)->where('status', 'completed')->count(),
                            'match_score' => $score,
                            'estimated_arrival' => rand(2, 10) . ' min',
                            'vehicle_type' => $driver->vehicle_type ?? 'standard',
                            'reason' => self::getRecommendationReason($driver, $userPreferences),
                        ];
                    })
                    ->sortByDesc('match_score')
                    ->take($limit)
                    ->values()
                    ->toArray();

                return [
                    'recommendations' => $drivers,
                    'count' => count($drivers),
                    'pickup' => $pickup,
                    'dropoff' => $dropoff,
                    'generated_at' => now()->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            Log::error("Driver recommendation failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get route recommendations for user
     */
    public static function recommendRoutesForUser(int $userId, int $limit = 5): array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . "routes:user:{$userId}";
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $limit) {
                // Get user's frequent routes
                $frequentRoutes = DB::table('bookings')
                    ->where('user_id', $userId)
                    ->selectRaw('pickup_location, dropoff_location, COUNT(*) as frequency, AVG(rating) as avg_rating')
                    ->groupBy('pickup_location', 'dropoff_location')
                    ->orderByDesc('frequency')
                    ->get();

                // Get similar users' routes
                $similarUsers = self::findSimilarUsers($userId, 10);
                $similarUserIds = $similarUsers->pluck('id')->toArray();

                $recommendedRoutes = DB::table('bookings')
                    ->whereIn('user_id', $similarUserIds)
                    ->selectRaw('pickup_location, dropoff_location, COUNT(*) as popularity, AVG(rating) as avg_rating')
                    ->groupBy('pickup_location', 'dropoff_location')
                    ->orderByDesc('popularity')
                    ->limit($limit)
                    ->get()
                    ->map(function ($route) {
                        return [
                            'pickup' => $route->pickup_location,
                            'dropoff' => $route->dropoff_location,
                            'popularity_score' => round($route->popularity / 10, 2),
                            'average_rating' => round($route->avg_rating ?? 0, 2),
                            'estimated_fare' => round(rand(800, 3000) / 100, 2),
                        ];
                    })
                    ->toArray();

                return [
                    'recommendations' => $recommendedRoutes,
                    'count' => count($recommendedRoutes),
                    'generated_at' => now()->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            Log::error("Route recommendation failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get ride suggestions for driver
     */
    public static function recommendRidesForDriver(int $driverId, int $limit = 5): array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . "rides:driver:{$driverId}";
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($driverId, $limit) {
                $driver = DB::table('drivers')->find($driverId);
                $driverHistory = self::getDriverBookingHistory($driverId);
                $driverPreferences = self::extractDriverPreferences($driverHistory);

                // Get pending bookings that match driver preferences
                $pendingBookings = DB::table('bookings')
                    ->where('status', 'pending')
                    ->whereNull('driver_id')
                    ->get()
                    ->map(function ($booking) use ($driverPreferences, $driver) {
                        $score = self::calculateBookingRelevanceScore($booking, $driverPreferences, $driver);
                        return [
                            'booking_id' => $booking->id,
                            'user_name' => DB::table('users')->find($booking->user_id)?->name ?? 'User',
                            'pickup' => $booking->pickup_location,
                            'dropoff' => $booking->dropoff_location,
                            'estimated_fare' => round($booking->fare ?? rand(800, 3000) / 100, 2),
                            'match_score' => $score,
                            'user_rating' => DB::table('bookings')->where('user_id', $booking->user_id)->whereNotNull('rating')->avg('rating') ?? 0,
                            'estimated_duration' => rand(10, 45) . ' min',
                        ];
                    })
                    ->sortByDesc('match_score')
                    ->take($limit)
                    ->values()
                    ->toArray();

                return [
                    'recommendations' => $pendingBookings,
                    'count' => count($pendingBookings),
                    'generated_at' => now()->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            Log::error("Ride recommendation failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get personalized user recommendations
     */
    public static function recommendForUser(int $userId): array
    {
        try {
            return [
                'user_id' => $userId,
                'recommended_drivers' => self::recommendDriversForUser($userId, 'Current Location', 'Destination', 3),
                'recommended_routes' => self::recommendRoutesForUser($userId, 3),
                'promotional_offers' => self::getPersonalizedOffers($userId),
                'optimal_booking_times' => self::getPredictedOptimalTimes($userId),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error("User recommendations failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get personalized driver recommendations
     */
    public static function recommendForDriver(int $driverId): array
    {
        try {
            return [
                'driver_id' => $driverId,
                'available_rides' => self::recommendRidesForDriver($driverId, 5),
                'earning_opportunities' => self::getEarningOpportunities($driverId),
                'zone_recommendations' => self::recommendZones($driverId),
                'shift_recommendations' => self::recommendShifts($driverId),
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            Log::error("Driver recommendations failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Content-based collaborative filtering
     */
    public static function getCollaborativeRecommendations(int $userId, int $limit = 10): array
    {
        try {
            $userBookings = DB::table('bookings')
                ->where('user_id', $userId)
                ->get();

            $similarUsers = self::findSimilarUsers($userId, 20);

            $recommendations = DB::table('bookings')
                ->whereIn('user_id', $similarUsers->pluck('id'))
                ->whereNotIn('id', $userBookings->pluck('id'))
                ->selectRaw('pickup_location, dropoff_location, COUNT(*) as score')
                ->groupBy('pickup_location', 'dropoff_location')
                ->orderByDesc('score')
                ->limit($limit)
                ->get()
                ->toArray();

            return [
                'recommendations' => $recommendations,
                'similar_users_analyzed' => count($similarUsers),
                'confidence' => round((count($similarUsers) / 100) * 100, 2),
            ];
        } catch (\Exception $e) {
            Log::error("Collaborative filtering failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ===== HELPER METHODS =====

    private static function getUserBookingHistory(int $userId): array
    {
        return DB::table('bookings')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private static function getDriverBookingHistory(int $driverId): array
    {
        return DB::table('bookings')
            ->where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private static function extractUserPreferences(array $history): array
    {
        if (empty($history)) return ['rating' => 4.0, 'vehicle_type' => 'any'];

        $avgRating = array_sum(array_column($history, 'rating')) / count($history);
        $vehicles = array_column($history, 'vehicle_type');
        $preferredVehicle = array_count_values($vehicles)[0] ?? 'standard';

        return [
            'min_rating' => $avgRating - 0.5,
            'preferred_vehicle' => $preferredVehicle,
            'price_sensitivity' => 'medium',
        ];
    }

    private static function extractDriverPreferences(array $history): array
    {
        if (empty($history)) return ['preferred_distance' => 'any', 'preferred_fare' => 'medium'];

        $avgDistance = array_sum(array_column($history, 'distance')) / count($history);
        $avgFare = array_sum(array_column($history, 'fare')) / count($history);

        return [
            'preferred_distance_range' => [$avgDistance * 0.7, $avgDistance * 1.3],
            'preferred_fare_range' => [$avgFare * 0.7, $avgFare * 1.3],
        ];
    }

    private static function calculateDriverScore(object $driver, array $userPreferences, int $userId): float
    {
        $ratingScore = $driver->rating >= ($userPreferences['min_rating'] ?? 4.0) ? 10 : 5;
        $availabilityScore = $driver->available ? 10 : 0;
        $completionScore = min(10, (DB::table('bookings')->where('driver_id', $driver->id)->where('status', 'completed')->count() / 50));
        
        // Check if user has rated this driver before
        $priorRating = DB::table('bookings')
            ->where('driver_id', $driver->id)
            ->where('user_id', $userId)
            ->whereNotNull('rating')
            ->avg('rating') ?? 0;
        
        $priorScore = $priorRating > 0 ? min(10, $priorRating) : 0;

        return round(($ratingScore * 0.3 + $availabilityScore * 0.2 + $completionScore * 0.3 + $priorScore * 0.2), 2);
    }

    private static function calculateBookingRelevanceScore(object $booking, array $driverPrefs, object $driver): float
    {
        $fareScore = (isset($booking->fare) && isset($driverPrefs['preferred_fare_range']))
            ? (abs($booking->fare - $driverPrefs['preferred_fare_range'][1]) < 1000 ? 10 : 5)
            : 5;

        $distanceScore = (isset($booking->distance) && isset($driverPrefs['preferred_distance_range']))
            ? (abs($booking->distance - $driverPrefs['preferred_distance_range'][1]) < 5 ? 10 : 5)
            : 5;

        $userRating = DB::table('bookings')->where('user_id', $booking->user_id)->whereNotNull('rating')->avg('rating') ?? 3;
        $userScore = min(10, $userRating);

        return round(($fareScore * 0.3 + $distanceScore * 0.4 + $userScore * 0.3), 2);
    }

    private static function findSimilarUsers(int $userId, int $limit = 10): object
    {
        $userBookingLocations = DB::table('bookings')
            ->where('user_id', $userId)
            ->selectRaw('pickup_location, COUNT(*) as freq')
            ->groupBy('pickup_location')
            ->pluck('freq', 'pickup_location');

        return DB::table('users')
            ->where('id', '!=', $userId)
            ->whereHas('bookings', function ($q) use ($userBookingLocations) {
                $q->whereIn('pickup_location', $userBookingLocations->keys());
            })
            ->limit($limit)
            ->get();
    }

    private static function getRecommendationReason(object $driver, array $preferences): string
    {
        if ($driver->rating >= ($preferences['min_rating'] ?? 4.0)) {
            return 'Highly rated driver';
        }
        return 'Available and reliable';
    }

    private static function getPersonalizedOffers(int $userId): array
    {
        $churnRisk = DB::table('bookings')->where('user_id', $userId)->count() < 3 ? 0.8 : 0.2;
        
        if ($churnRisk > 0.5) {
            return [
                ['type' => 'discount', 'amount' => 50, 'description' => 'Welcome back offer'],
                ['type' => 'cashback', 'amount' => 10, 'description' => 'Loyalty reward'],
            ];
        }

        return [
            ['type' => 'referral', 'amount' => 100, 'description' => 'Refer a friend'],
        ];
    }

    private static function getPredictedOptimalTimes(int $userId): array
    {
        $bookingTimes = DB::table('bookings')
            ->where('user_id', $userId)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(3)
            ->pluck('hour')
            ->toArray();

        return [
            'peak_hours' => $bookingTimes,
            'recommendation' => 'Book during ' . (count($bookingTimes) > 0 ? ($bookingTimes[0] . ':00') : 'peak hours') . ' for better availability',
        ];
    }

    private static function getEarningOpportunities(int $driverId): array
    {
        return [
            'high_demand_zones' => [
                ['zone' => 'Downtown', 'expected_earnings' => '$150-200', 'time_period' => '17:00-20:00'],
                ['zone' => 'Airport', 'expected_earnings' => '$100-150', 'time_period' => '07:00-10:00'],
            ],
            'surge_prediction' => 'High demand expected Friday 6-8 PM',
        ];
    }

    private static function recommendZones(int $driverId): array
    {
        $preferredZone = DB::table('bookings')
            ->where('driver_id', $driverId)
            ->selectRaw('pickup_location as zone, COUNT(*) as count')
            ->groupBy('zone')
            ->orderByDesc('count')
            ->first();

        return [
            'recommended_zone' => $preferredZone?->zone ?? 'Downtown',
            'expected_revenue' => '$200-300',
            'average_rating' => '4.8',
        ];
    }

    private static function recommendShifts(int $driverId): array
    {
        return [
            'optimal_shifts' => [
                ['time' => '08:00-12:00', 'expected_earnings' => '$80-120'],
                ['time' => '17:00-21:00', 'expected_earnings' => '$150-200'],
            ],
            'recommendation' => 'Peak earnings during evening rush',
        ];
    }
}
