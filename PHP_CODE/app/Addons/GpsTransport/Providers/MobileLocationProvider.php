<?php

namespace App\Addons\GpsTransport\Providers;

use App\Addons\GpsTransport\Contracts\LocationProviderInterface;
use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * MobileLocationProvider
 *
 * Implements mobile-phone GPS ingestion from the Driver Mobile App.
 * Handles battery state, network type (including offline sync tags), and OS mock location flags.
 */
class MobileLocationProvider implements LocationProviderInterface
{
    public function receiveLocation(array $payload): NormalizedLocation
    {
        return new NormalizedLocation([
            'vehicle_id'        => $payload['vehicle_id'] ?? 0,
            'driver_id'         => $payload['driver_id'] ?? null,
            'trip_id'           => $payload['trip_id'] ?? null,
            'latitude'          => (float) ($payload['latitude'] ?? 0.0),
            'longitude'         => (float) ($payload['longitude'] ?? 0.0),
            'accuracy'          => isset($payload['accuracy']) ? (float) $payload['accuracy'] : null,
            'altitude'          => isset($payload['altitude']) ? (float) $payload['altitude'] : null,
            'speed'             => (float) ($payload['speed'] ?? 0.0),
            'heading'           => (float) ($payload['heading'] ?? 0.0),
            'timestamp'         => isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : Carbon::now(),
            'battery_level'     => isset($payload['battery_level']) ? (int) $payload['battery_level'] : null,
            'is_mock_location'  => (bool) ($payload['is_mock_location'] ?? false),
            'source_type'       => 'MOBILE_APP',
            'source_identifier' => $payload['device_id'] ?? ($payload['source_identifier'] ?? 'mobile_driver_app'),
            'network_type'      => $payload['network_type'] ?? '4G',
            'extra_metadata'    => [
                'provider'       => 'mobile_gps',
                'app_version'    => $payload['app_version'] ?? '1.0.0',
                'os'             => $payload['os'] ?? 'android/ios',
                'is_background'  => (bool) ($payload['is_background'] ?? false),
            ],
        ]);
    }

    public function getCurrentLocation(int $vehicleId): ?NormalizedLocation
    {
        // 1. Try fast in-memory cache
        $cached = Cache::get("gps_vehicle_state_{$vehicleId}");
        if ($cached instanceof NormalizedLocation) {
            return $cached;
        }

        // 2. Query vehicle_current_states table
        $state = VehicleCurrentState::find($vehicleId);
        if ($state && $state->latitude && $state->longitude) {
            return new NormalizedLocation([
                'vehicle_id'       => $state->vehicle_id,
                'driver_id'        => $state->driver_id,
                'trip_id'          => $state->trip_id,
                'latitude'         => (float) $state->latitude,
                'longitude'        => (float) $state->longitude,
                'speed'            => (float) $state->speed,
                'heading'          => (float) $state->heading,
                'accuracy'         => (float) ($state->accuracy ?? 10.0),
                'timestamp'        => $state->last_location_at ?? $state->updated_at,
                'battery_level'    => $state->battery_level,
                'is_mock_location' => (bool) $state->is_mock_location,
                'source_type'      => 'MOBILE_APP',
                'network_type'     => $state->network_type ?? '4G',
            ]);
        }

        return null;
    }

    public function getProviderStatus(int $vehicleId): array
    {
        $current = $this->getCurrentLocation($vehicleId);
        if (!$current) {
            return [
                'connected'     => false,
                'status'        => 'OFFLINE',
                'battery_level' => null,
                'source_type'   => 'MOBILE_APP',
                'last_seen_at'  => null,
                'seconds_ago'   => null,
            ];
        }

        $secondsAgo = Carbon::now()->diffInSeconds($current->timestamp);
        $staleThreshold = config('gpstransport.defaults.stale_threshold_seconds', 300);

        return [
            'connected'     => ($secondsAgo <= $staleThreshold),
            'status'        => ($secondsAgo <= $staleThreshold) ? 'ONLINE' : 'STALE',
            'battery_level' => $current->batteryLevel,
            'source_type'   => 'MOBILE_APP',
            'last_seen_at'  => $current->timestamp->toIso8601String(),
            'seconds_ago'   => $secondsAgo,
        ];
    }

    public function getSourceType(): string
    {
        return 'MOBILE_APP';
    }
}
