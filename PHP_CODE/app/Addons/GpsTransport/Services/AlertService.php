<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsAlert;
use App\Addons\GpsTransport\Models\GpsTrip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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

        // 1. Overspeed Check
        $speedLimit = config('gpstransport.defaults.speed_limit_kmh', 45.0);
        $overspeedDuration = config('gpstransport.defaults.overspeed_duration_seconds', 20);

        if ($location->speed > $speedLimit) {
            $cacheKey = "gps_overspeed_start_{$location->vehicleId}";
            $firstSeen = Cache::get($cacheKey);

            if (!$firstSeen) {
                Cache::put($cacheKey, Carbon::now()->timestamp, 3600);
            } else {
                $duration = Carbon::now()->timestamp - (int) $firstSeen;
                if ($duration >= $overspeedDuration) {
                    $alert = $this->createAlert(
                        $trip?->id,
                        $location->vehicleId,
                        'OVERSPEED',
                        'WARNING',
                        "Vehicle speed {$location->speed} km/h exceeded limit of {$speedLimit} km/h for {$duration}s",
                        $location
                    );
                    if ($alert) {
                        $alertsGenerated[] = $alert;
                    }
                }
            }
        } else {
            Cache::forget("gps_overspeed_start_{$location->vehicleId}");
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
     * Create an alert with debouncing (won't create duplicate identical alert within 3 minutes).
     */
    public function createAlert(
        ?int $tripId,
        int $vehicleId,
        string $alertType,
        string $severity,
        string $message,
        ?NormalizedLocation $location = null
    ): ?GpsAlert {
        $debounceKey = "gps_alert_debounce_{$vehicleId}_{$alertType}";
        if (Cache::has($debounceKey)) {
            return null; // Debounced
        }

        try {
            $alert = GpsAlert::create([
                'trip_id'     => $tripId,
                'vehicle_id'  => $vehicleId,
                'alert_type'  => $alertType,
                'severity'    => $severity,
                'message'     => $message,
                'latitude'    => $location?->latitude,
                'longitude'   => $location?->longitude,
                'speed'       => $location?->speed ?? 0.0,
                'is_resolved' => false,
            ]);

            // Debounce for 3 minutes to avoid flooding the audit log
            Cache::put($debounceKey, true, 180);

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
}
