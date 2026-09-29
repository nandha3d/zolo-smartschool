<?php

namespace App\Addons\GpsTransport\DTO;

use Carbon\Carbon;

/**
 * NormalizedLocation DTO
 *
 * Represents a clean, validated, hardware-agnostic telemetry packet.
 * Conforms to vehicle_locations schema defined in Section 11 of the specification.
 */
class NormalizedLocation
{
    public int $vehicleId;
    public ?int $driverId;
    public ?int $tripId;
    public float $latitude;
    public float $longitude;
    public ?float $accuracy; // in meters
    public ?float $altitude; // in meters
    public float $speed; // in km/h
    public float $heading; // in degrees (0-360)
    public Carbon $timestamp;
    public Carbon $receivedAt;
    public ?int $batteryLevel; // 0-100%
    public bool $isMockLocation;
    public string $sourceType; // 'MOBILE_APP' | 'GPS_DEVICE'
    public ?string $sourceIdentifier; // Device UUID or IMEI
    public ?string $networkType; // 'WIFI' | '4G' | '5G' | 'OFFLINE_SYNC'
    public array $extraMetadata;

    public function __construct(array $attributes = [])
    {
        $this->vehicleId = (int) ($attributes['vehicle_id'] ?? 0);
        $this->driverId = isset($attributes['driver_id']) ? (int) $attributes['driver_id'] : null;
        $this->tripId = isset($attributes['trip_id']) ? (int) $attributes['trip_id'] : null;
        $this->latitude = (float) ($attributes['latitude'] ?? 0.0);
        $this->longitude = (float) ($attributes['longitude'] ?? 0.0);
        $this->accuracy = isset($attributes['accuracy']) ? (float) $attributes['accuracy'] : null;
        $this->altitude = isset($attributes['altitude']) ? (float) $attributes['altitude'] : null;
        $this->speed = max(0.0, (float) ($attributes['speed'] ?? 0.0));
        $this->heading = fmod(max(0.0, (float) ($attributes['heading'] ?? 0.0)), 360.0);
        
        $this->timestamp = isset($attributes['timestamp']) 
            ? ($attributes['timestamp'] instanceof Carbon ? $attributes['timestamp'] : Carbon::parse($attributes['timestamp']))
            : Carbon::now();

        $this->receivedAt = Carbon::now();
        $this->batteryLevel = isset($attributes['battery_level']) ? (int) $attributes['battery_level'] : null;
        $this->isMockLocation = (bool) ($attributes['is_mock_location'] ?? false);
        $this->sourceType = $attributes['source_type'] ?? 'MOBILE_APP';
        $this->sourceIdentifier = $attributes['source_identifier'] ?? null;
        $this->networkType = $attributes['network_type'] ?? '4G';
        $this->extraMetadata = $attributes['extra_metadata'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'driver_id' => $this->driverId,
            'trip_id' => $this->tripId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'altitude' => $this->altitude,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'timestamp' => $this->timestamp->toIso8601String(),
            'received_at' => $this->receivedAt->toIso8601String(),
            'battery_level' => $this->batteryLevel,
            'is_mock_location' => $this->isMockLocation,
            'source_type' => $this->sourceType,
            'source_identifier' => $this->sourceIdentifier,
            'network_type' => $this->networkType,
            'extra_metadata' => $this->extraMetadata,
        ];
    }
}
