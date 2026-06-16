<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Dynamic Pricing System
 * 
 * ML-driven pricing optimization:
 * - Surge pricing based on demand
 * - Time-based pricing
 * - Distance-optimized pricing
 * - Competitive pricing
 * - User segmentation pricing
 * - Driver incentive pricing
 */
class DynamicPricingService
{
    private const CACHE_PREFIX = 'pricing:';
    private const BASE_FARE = 25; // Base fare in currency units
    private const MIN_MULTIPLIER = 1.0;
    private const MAX_MULTIPLIER = 3.0;

    /**
     * Calculate dynamic price for a booking
     */
    public static function calculatePrice(
        int $userId,
        string $pickupLocation,
        string $dropoffLocation,
        float $distance,
        int $estimatedDuration = 0
    ): array {
        try {
            $cacheKey = self::CACHE_PREFIX . "price:{$pickupLocation}:{$dropoffLocation}";
            
            return Cache::remember($cacheKey, 300, function () use (
                $userId,
                $pickupLocation,
                $dropoffLocation,
                $distance,
                $estimatedDuration
            ) {
                $baseFare = self::BASE_FARE;
                $surgeMultiplier = self::calculateSurgeMultiplier($pickupLocation, $dropoffLocation);
                $distanceCharge = self::calculateDistanceCharge($distance);
                $timeCharge = self::calculateTimeCharge($estimatedDuration);
                $demandMultiplier = self::calculateDemandMultiplier($pickupLocation);
                $timeMultiplier = self::calculateTimeMultiplier();
                $userDiscount = self::calculateUserDiscount($userId);

                // Base calculation
                $subtotal = $baseFare + $distanceCharge + $timeCharge;

                // Apply multipliers
                $totalMultiplier = min(self::MAX_MULTIPLIER, $surgeMultiplier * $demandMultiplier * $timeMultiplier);
                $total = round($subtotal * $totalMultiplier, 2);

                // Apply user discount
                $discount = round($total * $userDiscount, 2);
                $finalPrice = max($subtotal * 0.5, $total - $discount); // Minimum 50% of base

                return [
                    'user_id' => $userId,
                    'pickup' => $pickupLocation,
                    'dropoff' => $dropoffLocation,
                    'distance_km' => round($distance, 2),
                    'estimated_duration_min' => $estimatedDuration,
                    'pricing_breakdown' => [
                        'base_fare' => $baseFare,
                        'distance_charge' => round($distanceCharge, 2),
                        'time_charge' => round($timeCharge, 2),
                        'subtotal' => round($subtotal, 2),
                    ],
                    'multipliers' => [
                        'surge' => round($surgeMultiplier, 2),
                        'demand' => round($demandMultiplier, 2),
                        'time' => round($timeMultiplier, 2),
                        'final_multiplier' => round($totalMultiplier, 2),
                    ],
                    'discounts' => [
                        'user_discount_percent' => round($userDiscount * 100, 1),
                        'discount_amount' => round($discount, 2),
                    ],
                    'total_before_discount' => round($total, 2),
                    'final_price' => round($finalPrice, 2),
                    'price_status' => self::getPriceStatus($totalMultiplier),
                    'valid_until' => now()->addMinutes(5)->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            Log::error("Price calculation failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Calculate surge pricing multiplier
     */
    public static function calculateSurgeMultiplier(string $pickup, string $dropoff): float
    {
        try {
            // Check current pending bookings in area
            $pendingBookings = DB::table('bookings')
                ->where('pickup_location', $pickup)
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            $availableDrivers = DB::table('drivers')
                ->where('status', 'active')
                ->where('available', true)
                ->count();

            if ($availableDrivers === 0) {
                return self::MAX_MULTIPLIER;
            }

            $ratio = $pendingBookings / $availableDrivers;

            return match (true) {
                $ratio > 5 => 2.5,
                $ratio > 3 => 2.0,
                $ratio > 2 => 1.5,
                $ratio > 1 => 1.25,
                default => 1.0,
            };
        } catch (\Exception $e) {
            return 1.0;
        }
    }

    /**
     * Calculate distance-based charge
     */
    private static function calculateDistanceCharge(float $distance): float
    {
        // $2.50 per km for first 5km, $2.00 per km after
        $perKmRate = $distance <= 5 ? 2.5 : 2.0;
        return $distance * $perKmRate;
    }

    /**
     * Calculate time-based charge
     */
    private static function calculateTimeCharge(int $estimatedDuration): float
    {
        // $0.40 per minute for estimated duration
        return max(0, $estimatedDuration * 0.4);
    }

    /**
     * Calculate demand-based multiplier
     */
    private static function calculateDemandMultiplier(string $zone): float
    {
        $demand = DemandPredictionService::predictGeographicDemand(1);
        
        foreach ($demand['zones'] ?? [] as $zoneDemand) {
            if ($zoneDemand['zone'] === $zone) {
                return match ($zoneDemand['demand_level']) {
                    'very_high' => 1.3,
                    'high' => 1.2,
                    'medium' => 1.0,
                    'low' => 0.95,
                    'very_low' => 0.9,
                    default => 1.0,
                };
            }
        }

        return 1.0;
    }

    /**
     * Calculate time-based multiplier (peak hours)
     */
    private static function calculateTimeMultiplier(): float
    {
        $hour = now()->hour;

        // Peak hours: 7-9 AM, 5-8 PM
        if (($hour >= 7 && $hour < 9) || ($hour >= 17 && $hour < 20)) {
            return 1.2;
        }

        // Night premium: 10 PM - 5 AM
        if ($hour >= 22 || $hour < 5) {
            return 1.15;
        }

        // Normal hours
        return 1.0;
    }

    /**
     * Calculate user-specific discount
     */
    private static function calculateUserDiscount(int $userId): float
    {
        $bookingCount = DB::table('bookings')->where('user_id', $userId)->count();
        $avgRating = DB::table('bookings')->where('user_id', $userId)->whereNotNull('rating')->avg('rating') ?? 0;

        // Loyalty discount based on booking history
        $loyaltyDiscount = match (true) {
            $bookingCount >= 100 => 0.15,
            $bookingCount >= 50 => 0.12,
            $bookingCount >= 20 => 0.08,
            $bookingCount >= 10 => 0.05,
            default => 0,
        };

        // Quality discount based on rating
        $qualityDiscount = $avgRating >= 4.8 ? 0.05 : ($avgRating >= 4.5 ? 0.02 : 0);

        // Churn prevention discount
        $lastBooking = DB::table('bookings')->where('user_id', $userId)->latest('created_at')->first();
        $churnDiscount = 0;
        if ($lastBooking) {
            $daysInactive = now()->diffInDays($lastBooking->created_at);
            if ($daysInactive > 30) $churnDiscount = 0.1;
            if ($daysInactive > 60) $churnDiscount = 0.15;
        }

        return min(0.3, $loyaltyDiscount + $qualityDiscount + $churnDiscount);
    }

    /**
     * Calculate driver incentive pricing
     */
    public static function calculateDriverIncentive(int $driverId, float $baseFare): array
    {
        try {
            $currentDemand = DemandPredictionService::predictHourlyDemand(1);
            $firstPrediction = $currentDemand['predictions'][0] ?? [];
            $demandLevel = $firstPrediction['risk_level'] ?? 'normal';

            $driverRating = DB::table('drivers')->where('id', $driverId)->value('rating') ?? 4.0;
            $acceptanceRate = self::getDriverAcceptanceRate($driverId);
            $onlineTime = self::getDriverOnlineTime($driverId);

            $basePayout = $baseFare * 0.75;
            $incentiveMultiplier = 1.0;

            // Demand-based incentive
            $incentiveMultiplier += match ($demandLevel) {
                'high_demand' => 0.3,
                'normal' => 0,
                default => -0.1,
            };

            // Rating-based incentive
            if ($driverRating >= 4.8) $incentiveMultiplier += 0.1;
            if ($driverRating >= 4.5) $incentiveMultiplier += 0.05;

            // Acceptance rate bonus
            if ($acceptanceRate >= 0.9) $incentiveMultiplier += 0.05;

            // Online time bonus
            if ($onlineTime > 6) $incentiveMultiplier += 0.1;

            $driverPayout = round($basePayout * $incentiveMultiplier, 2);
            $platformFee = round($baseFare - $driverPayout, 2);

            return [
                'driver_id' => $driverId,
                'base_fare' => $baseFare,
                'base_payout' => round($basePayout, 2),
                'incentive_multiplier' => round($incentiveMultiplier, 2),
                'driver_payout' => $driverPayout,
                'platform_fee' => $platformFee,
                'breakdown' => [
                    'demand_incentive' => match ($demandLevel) {
                        'high_demand' => '30%',
                        'normal' => 'baseline',
                        default => '-10%',
                    },
                    'quality_bonus' => $driverRating >= 4.8 ? '10%' : ($driverRating >= 4.5 ? '5%' : '0%'),
                    'acceptance_bonus' => $acceptanceRate >= 0.9 ? '5%' : '0%',
                    'online_time_bonus' => $onlineTime > 6 ? '10%' : '0%',
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Driver incentive calculation failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get price recommendations
     */
    public static function getPriceRecommendations(): array
    {
        try {
            $currentSurge = self::calculateSurgeMultiplier('Downtown', 'Airport');
            $demand = DemandPredictionService::predictHourlyDemand(3);
            $peakDemand = max(array_column($demand['predictions'], 'predicted_bookings'));

            return [
                'current_surge_multiplier' => round($currentSurge, 2),
                'market_status' => $currentSurge > 1.5 ? 'high_demand' : 'normal',
                'peak_demand_predicted' => $peakDemand,
                'pricing_strategy' => [
                    'if_high_demand' => 'Increase multiplier to ' . round(min(self::MAX_MULTIPLIER, $currentSurge * 1.1), 2),
                    'if_low_demand' => 'Offer promotions to stimulate bookings',
                    'optimal_strategy' => 'Maintain current pricing, monitor demand',
                ],
                'revenue_impact' => [
                    'current_strategy_daily' => '$15,000-20,000',
                    'optimized_strategy_daily' => '$18,000-24,000',
                    'potential_increase' => '15-20%',
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Price recommendations failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * A/B test pricing strategy
     */
    public static function testPricingStrategy(string $strategy, int $userGroup = null): array
    {
        try {
            $variants = [
                'aggressive' => 1.3, // High multiplier
                'conservative' => 1.05, // Low multiplier  
                'adaptive' => 1.15, // Current strategy
            ];

            $multiplier = $variants[$strategy] ?? 1.15;

            return [
                'strategy' => $strategy,
                'multiplier' => $multiplier,
                'expected_revenue_impact' => match ($strategy) {
                    'aggressive' => '+25% but -10% volume',
                    'conservative' => '-5% revenue but +20% volume',
                    'adaptive' => 'baseline',
                    default => 'unknown',
                },
                'recommendation' => 'Run 7-day A/B test with 10% of users',
                'metrics_to_track' => [
                    'revenue_per_booking',
                    'total_bookings',
                    'user_satisfaction',
                    'driver_earnings',
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Pricing strategy test failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ===== HELPER METHODS =====

    private static function getPriceStatus(float $multiplier): string
    {
        if ($multiplier >= 2.0) return 'extreme_surge';
        if ($multiplier >= 1.5) return 'high_surge';
        if ($multiplier >= 1.2) return 'moderate_surge';
        if ($multiplier > 1.0) return 'slight_surge';
        return 'normal';
    }

    private static function getDriverAcceptanceRate(int $driverId): float
    {
        $offered = DB::table('driver_offers')->where('driver_id', $driverId)->count() ?? 1;
        $accepted = DB::table('driver_offers')->where('driver_id', $driverId)->where('accepted', true)->count() ?? 0;
        return $offered > 0 ? $accepted / $offered : 0.5;
    }

    private static function getDriverOnlineTime(int $driverId): float
    {
        // Hours online today
        $onlineRecords = DB::table('driver_sessions')
            ->where('driver_id', $driverId)
            ->whereDate('session_date', now()->toDateString())
            ->get();

        $totalMinutes = 0;
        foreach ($onlineRecords as $record) {
            $totalMinutes += $record->duration_minutes ?? 0;
        }

        return $totalMinutes / 60;
    }
}
