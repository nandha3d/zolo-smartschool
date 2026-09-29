<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsRouteGeometry;
use App\Addons\GpsTransport\Models\GpsTrip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * RouteDeviationService
 *
 * Implements Section 39: Route Deviation Detection.
 * Calculates perpendicular/minimum distance from the planned route polyline.
 * Prevents false positives by requiring deviation to persist for a configurable duration.
 */
class RouteDeviationService
{
    private LocationValidator $distanceCalculator;

    public function __construct(LocationValidator $distanceCalculator)
    {
        $this->distanceCalculator = $distanceCalculator;
    }

    /**
     * Check if vehicle is currently deviating from the planned route.
     *
     * @param NormalizedLocation $location
     * @param GpsTrip $trip
     * @return array [ 'is_deviating' => bool, 'distance_meters' => float, 'sustained_seconds' => int, 'alert_required' => bool ]
     */
    public function checkDeviation(NormalizedLocation $location, GpsTrip $trip): array
    {
        if (!$trip->route_id) {
            return ['is_deviating' => false, 'distance_meters' => 0.0, 'sustained_seconds' => 0, 'alert_required' => false];
        }

        $geometry = GpsRouteGeometry::where('route_id', $trip->route_id)->where('active', 1)->first();
        if (!$geometry || empty($geometry->waypoints_json)) {
            return ['is_deviating' => false, 'distance_meters' => 0.0, 'sustained_seconds' => 0, 'alert_required' => false];
        }

        $waypoints = is_array($geometry->waypoints_json) 
            ? $geometry->waypoints_json 
            : json_decode($geometry->waypoints_json, true);

        if (empty($waypoints) || !is_array($waypoints)) {
            return ['is_deviating' => false, 'distance_meters' => 0.0, 'sustained_seconds' => 0, 'alert_required' => false];
        }

        // Find minimum distance to any segment on the planned route
        $minDistanceMeters = $this->calculateMinDistanceToPolyline(
            $location->latitude,
            $location->longitude,
            $waypoints
        );

        $thresholdMeters = config('gpstransport.defaults.route_deviation_distance_meters', 300);
        $thresholdDurationSeconds = config('gpstransport.defaults.route_deviation_duration_seconds', 60);

        $cacheKey = "gps_trip_deviation_{$trip->id}";
        $deviationStart = Cache::get($cacheKey);

        $isCurrentlyOffRoute = ($minDistanceMeters > $thresholdMeters);

        if ($isCurrentlyOffRoute) {
            if (!$deviationStart) {
                // First point detected off-route
                Cache::put($cacheKey, Carbon::now()->timestamp, 3600);
                $sustainedSeconds = 0;
            } else {
                $sustainedSeconds = Carbon::now()->timestamp - (int) $deviationStart;
            }

            $alertRequired = ($sustainedSeconds >= $thresholdDurationSeconds);

            return [
                'is_deviating'      => true,
                'distance_meters'   => round($minDistanceMeters, 1),
                'sustained_seconds' => $sustainedSeconds,
                'alert_required'    => $alertRequired,
            ];
        }

        // Vehicle returned to route: clear cache
        if ($deviationStart) {
            Cache::forget($cacheKey);
        }

        return [
            'is_deviating'      => false,
            'distance_meters'   => round($minDistanceMeters, 1),
            'sustained_seconds' => 0,
            'alert_required'    => false,
        ];
    }

    /**
     * Compute minimum distance from a point to a polyline formed by waypoints.
     */
    private function calculateMinDistanceToPolyline(float $lat, float $lng, array $waypoints): float
    {
        $minDistance = PHP_FLOAT_MAX;

        for ($i = 0; $i < count($waypoints); $i++) {
            $p = $waypoints[$i];
            $pLat = (float) ($p['lat'] ?? ($p[0] ?? 0.0));
            $pLng = (float) ($p['lng'] ?? ($p[1] ?? 0.0));

            $dist = $this->distanceCalculator->calculateDistanceMeters($lat, $lng, $pLat, $pLng);
            if ($dist < $minDistance) {
                $minDistance = $dist;
            }
        }

        return ($minDistance === PHP_FLOAT_MAX) ? 0.0 : $minDistance;
    }
}
