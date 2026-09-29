<?php

namespace App\Addons\GpsTransport\Providers;

use App\Addons\GpsTransport\Contracts\LocationProviderInterface;
use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * FutureGpsDeviceProvider
 *
 * Implements dedicated vehicle hardware GPS device ingestion (Teltonika, Queclink, Concox, Coban, etc.).
 * Ready for Phase 2 hardware integration without modifying any downstream tracking, geofence,
 * ETA, notification, or presentation code.
 */
class FutureGpsDeviceProvider implements LocationProviderInterface
{
    public function receiveLocation(array $payload): NormalizedLocation
    {
        return new NormalizedLocation([
            'vehicle_id'        => $payload['vehicle_id'] ?? 0,
            'driver_id'         => $payload['driver_id'] ?? null,
            'trip_id'           => $payload['trip_id'] ?? null,
            'latitude'          => (float) ($payload['latitude'] ?? 0.0),
            'longitude'         => (float) ($payload['longitude'] ?? 0.0),
            'accuracy'          => isset($payload['accuracy']) ? (float) $payload['accuracy'] : 5.0,
            'altitude'          => isset($payload['altitude']) ? (float) $payload['altitude'] : null,
            'speed'             => (float) ($payload['speed'] ?? 0.0),
            'heading'           => (float) ($payload['heading'] ?? 0.0),
            'timestamp'         => isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : Carbon::now(),
            'battery_level'     => isset($payload['device_battery']) ? (int) $payload['device_battery'] : 100,
            'is_mock_location'  => false, // Hardware GPS cannot use software mock locations
            'source_type'       => 'GPS_DEVICE',
            'source_identifier' => $payload['imei'] ?? ($payload['source_identifier'] ?? 'HARDWARE_GPS'),
            'network_type'      => 'CELLULAR_4G',
            'extra_metadata'    => [
                'provider'       => 'dedicated_hardware_gps',
                'protocol'       => $payload['protocol'] ?? 'standard_gt06',
                'ignition'       => (bool) ($payload['ignition'] ?? true),
                'external_power' => (bool) ($payload['external_power'] ?? true),
            ],
        ]);
    }

    public function getCurrentLocation(int $vehicleId): ?NormalizedLocation
    {
        $cached = Cache::get("gps_hardware_vehicle_state_{$vehicleId}");
        if ($cached instanceof NormalizedLocation) {
            return $cached;
        }

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
                'accuracy'         => (float) ($state->accuracy ?? 5.0),
                'timestamp'        => $state->last_location_at ?? $state->updated_at,
                'battery_level'    => $state->battery_level ?? 100,
                'is_mock_location' => false,
                'source_type'      => 'GPS_DEVICE',
                'network_type'     => 'CELLULAR_4G',
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
                'source_type'   => 'GPS_DEVICE',
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
            'source_type'   => 'GPS_DEVICE',
            'last_seen_at'  => $current->timestamp->toIso8601String(),
            'seconds_ago'   => $secondsAgo,
        ];
    }

    public function getSourceType(): string
    {
        return 'GPS_DEVICE';
    }
}
