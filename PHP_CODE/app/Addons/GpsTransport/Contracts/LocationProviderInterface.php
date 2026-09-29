<?php

namespace App\Addons\GpsTransport\Contracts;

use App\Addons\GpsTransport\DTO\NormalizedLocation;

/**
 * Interface LocationProviderInterface
 *
 * Core architectural boundary defined in Section 3 & Section 80 of the GPS specification.
 * Downstream components (TrackingEngine, GeofenceService, ETAService, NotificationEngine)
 * depend strictly on this abstraction, allowing seamless transition from Mobile App GPS (Phase 1)
 * to Dedicated Hardware GPS Devices (Phase 2) without changing business logic.
 */
interface LocationProviderInterface
{
    /**
     * Ingest and normalize raw telemetry data from the location source.
     *
     * @param array $payload
     * @return NormalizedLocation
     */
    public function receiveLocation(array $payload): NormalizedLocation;

    /**
     * Retrieve the latest known normalized location for a vehicle.
     *
     * @param int $vehicleId
     * @return NormalizedLocation|null
     */
    public function getCurrentLocation(int $vehicleId): ?NormalizedLocation;

    /**
     * Retrieve current health and connectivity status of the location source.
     *
     * @param int $vehicleId
     * @return array ['connected' => bool, 'battery_level' => ?int, 'source_type' => string, 'last_seen_at' => ?string]
     */
    public function getProviderStatus(int $vehicleId): array;

    /**
     * Return source type identifier: MOBILE_APP or GPS_DEVICE.
     *
     * @return string
     */
    public function getSourceType(): string;
}
