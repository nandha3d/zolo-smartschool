<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\Contracts\LocationProviderInterface;
use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Providers\FutureGpsDeviceProvider;
use App\Addons\GpsTransport\Providers\MobileLocationProvider;
use InvalidArgumentException;

/**
 * LocationGateway
 *
 * Implements Section 3 & Section 86:
 * Location Provider -> Location Gateway -> Normalized Location -> Tracking Engine
 */
class LocationGateway
{
    private MobileLocationProvider $mobileProvider;
    private FutureGpsDeviceProvider $deviceProvider;
    private LocationValidator $validator;

    public function __construct(
        MobileLocationProvider $mobileProvider,
        FutureGpsDeviceProvider $deviceProvider,
        LocationValidator $validator
    ) {
        $this->mobileProvider = $mobileProvider;
        $this->deviceProvider = $deviceProvider;
        $this->validator = $validator;
    }

    /**
     * Resolve appropriate provider based on payload source_type.
     */
    public function getProvider(string $sourceType = 'MOBILE_APP'): LocationProviderInterface
    {
        return match (strtoupper($sourceType)) {
            'GPS_DEVICE', 'DEVICE', 'HARDWARE' => $this->deviceProvider,
            default => $this->mobileProvider,
        };
    }

    /**
     * Ingest, normalize, and validate telemetry payload.
     *
     * @param array $payload
     * @return array ['location' => NormalizedLocation, 'flags' => array]
     * @throws InvalidArgumentException
     */
    public function process(array $payload): array
    {
        $sourceType = $payload['source_type'] ?? 'MOBILE_APP';
        $provider = $this->getProvider($sourceType);
        
        $normalizedLocation = $provider->receiveLocation($payload);

        // Retrieve previous location for anomaly/jump detection
        $previousLocation = $provider->getCurrentLocation($normalizedLocation->vehicleId);

        $validation = $this->validator->validate($normalizedLocation, $previousLocation);

        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error'] ?? 'Invalid GPS telemetry');
        }

        return [
            'location' => $normalizedLocation,
            'flags'    => $validation['flags'],
        ];
    }
}
