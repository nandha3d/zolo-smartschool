<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsAlert;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AlertService
 *
 * Implements Sections 40, 42 & 66: Safety Events and Alert Rule Engine.
 * Manages rules, debouncing, persistence into gps_alerts, and notification escalations.
 */
class AlertService
{
    private GpsNotificationService $notificationService;

    public function __construct(GpsNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Evaluate telemetry for alerts.
     */
    public function evaluate(NormalizedLocation $location, ?GpsTrip $trip, array $deviationInfo = []): array
    {
        $alertsGenerated = [];

        // 1. Overspeed Check: the vehicle's own limit, else the school default.
        $vehicle = Vehicle::find($location->vehicleId);
        $speedLimit = (float) ($vehicle?->speed_limit_kmh ?: config('gpstransport.defaults.speed_limit_kmh', 45.0));
        $overspeedDuration = (int) config('gpstransport.defaults.overspeed_duration_seconds', 20);
        $startKey = $this->cacheKey("overspeed_start_{$location->vehicleId}");
        $peakKey = $this->cacheKey("overspeed_peak_{$location->vehicleId}");

        if ($location->speed > $speedLimit) {
            $firstSeen = Cache::get($startKey);
            $peak = max((float) Cache::get($peakKey, 0), (float) $location->speed);
            Cache::put($peakKey, $peak, 3600);

            if (!$firstSeen) {
                Cache::put($startKey, Carbon::now()->timestamp, 3600);
            } else {
                $duration = Carbon::now()->timestamp - (int) $firstSeen;
                if ($duration >= $overspeedDuration) {
                    $label = $vehicle?->vehicle_number ?: "Vehicle #{$location->vehicleId}";
                    $alert = $this->createAlert(
                        $trip?->id,
                        $location->vehicleId,
                        'OVERSPEED',
                        'CRITICAL',
                        "{$label} at {$location->speed} km/h (peak {$peak} km/h), over the {$speedLimit} km/h limit for {$duration}s",
                        $location,
                        [
                            'driver_id'        => $trip?->driver_id,
                            'route_id'         => $trip?->route_id,
                            'speed_limit'      => $speedLimit,
                            'peak_speed'       => $peak,
                            'duration_seconds' => $duration,
                        ],
                        (int) ($vehicle?->speed_alert_cooldown_minutes ?: 3) * 60
                    );
                    if ($alert) {
                        $alertsGenerated[] = $alert;
                    }
                }
            }
        } else {
            Cache::forget($startKey);
            Cache::forget($peakKey);
        }

        // 2. Route Deviation Alert
        if (!empty($deviationInfo['alert_required'])) {
            $alert = $this->createAlert(
                $trip?->id,
                $location->vehicleId,
                'ROUTE_DEVIATION',
                'CRITICAL',
                "Vehicle deviated {$deviationInfo['distance_meters']}m from planned route for {$deviationInfo['sustained_seconds']}s",
                $location
            );
            if ($alert) {
                $alertsGenerated[] = $alert;
            }
        }

        // 3. Mock Location Warning
        if ($location->isMockLocation) {
            $alert = $this->createAlert(
                $trip?->id,
                $location->vehicleId,
                'MOCK_LOCATION_WARNING',
                'WARNING',
                "Driver device reported mock / simulated GPS coordinates",
                $location
            );
            if ($alert) {
                $alertsGenerated[] = $alert;
            }
        }

        return $alertsGenerated;
    }

    /**
     * Create an alert with debouncing (no duplicate of the same type for the same vehicle
     * within the cooldown, 3 minutes unless the vehicle sets its own).
     */
    public function createAlert(
        ?int $tripId,
        int $vehicleId,
        string $alertType,
        string $severity,
        string $message,
        ?NormalizedLocation $location = null,
        array $extra = [],
        int $cooldownSeconds = 180
    ): ?GpsAlert {
        $debounceKey = $this->cacheKey("alert_debounce_{$vehicleId}_{$alertType}");
        if (Cache::has($debounceKey)) {
            return null; // Debounced
        }

        try {
            $alert = GpsAlert::create(array_merge([
                'trip_id'     => $tripId,
                'vehicle_id'  => $vehicleId,
                'alert_type'  => $alertType,
                'severity'    => $severity,
                'message'     => $message,
                'latitude'    => $location?->latitude,
                'longitude'   => $location?->longitude,
                'speed'       => $location?->speed ?? 0.0,
                'is_resolved' => false,
            ], $extra));

            Cache::put($debounceKey, true, max(60, $cooldownSeconds));

            // Escalate critical alerts to notifications
            if (in_array($severity, ['CRITICAL', 'WARNING'])) {
                $this->notificationService->sendSafetyAlertNotification($alert);
            }

            return $alert;
        } catch (\Throwable $e) {
            Log::warning("Could not persist GPS alert: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Every school has its own vehicle #1, so cache keys carry the tenant database name.
     */
    private function cacheKey(string $key): string
    {
        return 'gps_' . DB::connection()->getDatabaseName() . '_' . $key;
    }
}
