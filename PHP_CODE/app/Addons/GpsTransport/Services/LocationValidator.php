<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use Carbon\Carbon;

/**
 * LocationValidator
 *
 * Implements GPS Data Validation & Mock GPS Detection (Sections 12 & 13 of the specification).
 * Filters out invalid latitude/longitude, unreasonable speeds, inaccurate telemetry (> 100m),
 * and flags suspicious mock GPS coordinates.
 */
class LocationValidator
{
    /**
     * Validate normalized location.
     *
     * @param NormalizedLocation $location
     * @param NormalizedLocation|null $previousLocation
     * @return array ['valid' => bool, 'flags' => array, 'error' => ?string]
     */
    public function validate(NormalizedLocation $location, ?NormalizedLocation $previousLocation = null): array
    {
        $flags = [];

        // 1. Latitude bounds [-90, 90]
        if ($location->latitude < -90.0 || $location->latitude > 90.0 || ($location->latitude == 0.0 && $location->longitude == 0.0)) {
            return [
                'valid' => false,
                'flags' => ['INVALID_COORDINATES'],
                'error' => 'Latitude must be between -90 and 90 and not (0,0)',
            ];
        }

        // 2. Longitude bounds [-180, 180]
        if ($location->longitude < -180.0 || $location->longitude > 180.0) {
            return [
                'valid' => false,
                'flags' => ['INVALID_COORDINATES'],
                'error' => 'Longitude must be between -180 and 180',
            ];
        }

        // 3. Timestamp bounds: cannot be more than 2 minutes in future
        $now = Carbon::now();
        if ($location->timestamp->greaterThan($now->copy()->addMinutes(2))) {
            return [
                'valid' => false,
                'flags' => ['FUTURE_TIMESTAMP'],
                'error' => 'GPS timestamp cannot be in the future',
            ];
        }

        // Flag stale timestamps (> 24 hours old)
        if ($location->timestamp->lessThan($now->copy()->subHours(24))) {
            $flags[] = 'OLD_OFFLINE_TIMESTAMP';
        }

        // 4. Accuracy validation
        $accuracyThreshold = config('gpstransport.defaults.accuracy_threshold_meters', 100);
        if ($location->accuracy !== null && $location->accuracy > $accuracyThreshold) {
            $flags[] = 'LOW_ACCURACY';
        }

        // 5. Mock GPS Detection (Section 13)
        if ($location->isMockLocation) {
            $flags[] = 'MOCK_GPS_DETECTED';
        }

        // 6. Unrealistic Speed / Teleport Detection
        if ($previousLocation !== null && $previousLocation->latitude != 0.0) {
            $timeDiffSeconds = abs($location->timestamp->diffInSeconds($previousLocation->timestamp));
            if ($timeDiffSeconds > 0) {
                $distanceKm = $this->calculateDistanceKm(
                    $previousLocation->latitude,
                    $previousLocation->longitude,
                    $location->latitude,
                    $location->longitude
                );
                
                $impliedSpeedKmh = ($distanceKm / ($timeDiffSeconds / 3600.0));
                
                // If implied speed exceeds 160 km/h (e.g. school bus teleporting between cell towers)
                if ($impliedSpeedKmh > 160.0 && $distanceKm > 0.5) {
                    $flags[] = 'UNREALISTIC_MOVEMENT_JUMP';
                }
            }
        }

        return [
            'valid' => true,
            'flags' => $flags,
            'error' => null,
        ];
    }

    /**
     * Calculate Haversine distance in kilometers between two lat/lng coordinates.
     */
    public function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if ($lat1 === $lat2 && $lon1 === $lon2) {
            return 0.0;
        }

        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Calculate distance in meters.
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return $this->calculateDistanceKm($lat1, $lon1, $lat2, $lon2) * 1000.0;
    }
}
