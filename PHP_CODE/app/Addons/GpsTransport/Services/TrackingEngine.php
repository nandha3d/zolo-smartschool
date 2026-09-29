<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsSession;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Addons\GpsTransport\Models\VehicleLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TrackingEngine
 *
 * Implements Section 20, 21, 65 & 85: Tracking Engine & Event Engine.
 * Ingests validated NormalizedLocation objects and orchestrates:
 * - Current vehicle state persistence (Redis/Cache + DB)
 * - Historical raw breadcrumbs (vehicle_locations)
 * - Active trip distance accumulation
 * - Geofence evaluation (stop state machine)
 * - Dynamic ETA updates
 * - Route deviation tracking
 * - Driver safety alerts (overspeed, long stops, offline)
 * - Real-time WebSocket broadcasting
 */
class TrackingEngine
{
    private GeofenceService $geofenceService;
    private ETAService $etaService;
    private RouteDeviationService $deviationService;
    private AlertService $alertService;
    private GpsBroadcaster $broadcaster;
    private LocationValidator $validator;

    public function __construct(
        GeofenceService $geofenceService,
        ETAService $etaService,
        RouteDeviationService $deviationService,
        AlertService $alertService,
        GpsBroadcaster $broadcaster,
        LocationValidator $validator
    ) {
        $this->geofenceService = $geofenceService;
        $this->etaService = $etaService;
        $this->deviationService = $deviationService;
        $this->alertService = $alertService;
        $this->broadcaster = $broadcaster;
        $this->validator = $validator;
    }

    /**
     * Process a normalized location point.
     *
     * @param NormalizedLocation $location
     * @param array $validationFlags
     * @return array Ingestion summary and triggered events
     */
    public function ingest(NormalizedLocation $location, array $validationFlags = []): array
    {
        // 1. Resolve Active Trip
        $trip = null;
        if ($location->tripId) {
            $trip = GpsTrip::with(['route', 'vehicle', 'driver'])->find($location->tripId);
        }

        if (!$trip && $location->vehicleId) {
            $trip = GpsTrip::with(['route', 'vehicle', 'driver'])
                ->where('vehicle_id', $location->vehicleId)
                ->where('status', 'ACTIVE')
                ->latest()
                ->first();
            if ($trip) {
                $location->tripId = $trip->id;
            }
        }

        // 2. Determine Dynamic Vehicle Status
        $vehicleStatus = 'STOPPED';
        if ($trip && $trip->status === 'EMERGENCY') {
            $vehicleStatus = 'EMERGENCY';
        } elseif ($location->speed >= 3.0) {
            $vehicleStatus = 'MOVING';
        }

        // 3. Evaluate Geofencing & Stop State Machine
        $geofenceEvents = $this->geofenceService->evaluate($location, $trip);

        // 4. Calculate dynamic ETAs
        $etaData = [];
        if ($trip) {
            $etaData = $this->etaService->calculate($location, $trip);
        }

        // 5. Evaluate Route Deviation
        $deviationInfo = [];
        if ($trip) {
            $deviationInfo = $this->deviationService->checkDeviation($location, $trip);
        }

        // 6. Evaluate Driver Safety Alerts
        $alerts = $this->alertService->evaluate($location, $trip, $deviationInfo);

        // 7. Persist Historical Location Breadcrumb (vehicle_locations)
        $this->recordHistoricalLocation($location, $validationFlags);

        // 8. Update Fast-Access Current State (vehicle_current_states & Cache)
        $this->updateCurrentState($location, $trip, $vehicleStatus, $etaData);

        // 9. Update Active Trip Statistics (Distance & Session)
        if ($trip) {
            $this->updateTripMetrics($trip, $location);
        }

        // 10. Broadcast Real-Time Update
        $this->broadcaster->broadcastLocation($location, $etaData, $vehicleStatus);

        return [
            'success'          => true,
            'vehicle_id'       => $location->vehicleId,
            'trip_id'          => $location->tripId,
            'status'           => $vehicleStatus,
            'geofence_events'  => $geofenceEvents,
            'alerts_triggered' => count($alerts),
            'eta'              => $etaData['destination_eta'] ?? null,
            'next_stop'        => $etaData['next_stop']['stop_name'] ?? null,
        ];
    }

    /**
     * Store raw historical breadcrumb for trip replay and reporting.
     */
    private function recordHistoricalLocation(NormalizedLocation $location, array $flags = []): void
    {
        try {
            VehicleLocation::create([
                'vehicle_id'        => $location->vehicleId,
                'driver_id'         => $location->driverId,
                'trip_id'           => $location->tripId,
                'source_type'       => $location->sourceType,
                'source_id'         => $location->sourceIdentifier,
                'latitude'          => $location->latitude,
                'longitude'         => $location->longitude,
                'accuracy'          => $location->accuracy,
                'altitude'          => $location->altitude,
                'speed'             => $location->speed,
                'heading'           => $location->heading,
                'timestamp'         => $location->timestamp,
                'received_at'       => $location->receivedAt,
                'battery_level'     => $location->batteryLevel,
                'is_mock_location'  => $location->isMockLocation,
                'network_type'      => $location->networkType,
                'flags'             => !empty($flags) ? json_encode($flags) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Could not persist vehicle_location: {$e->getMessage()}");
        }
    }

    /**
     * Update fast-access vehicle_current_states table and cache.
     */
    private function updateCurrentState(
        NormalizedLocation $location,
        ?GpsTrip $trip,
        string $status,
        array $etaData
    ): void {
        $stateAttributes = [
            'vehicle_id'       => $location->vehicleId,
            'trip_id'          => $location->tripId,
            'driver_id'        => $location->driverId,
            'latitude'         => $location->latitude,
            'longitude'        => $location->longitude,
            'speed'            => $location->speed,
            'heading'          => $location->heading,
            'status'           => $status,
            'next_stop_id'     => $etaData['next_stop']['stop_id'] ?? null,
            'eta'              => $etaData['destination_eta'] ?? null,
            'gps_status'       => 'CONNECTED',
            'battery_level'    => $location->batteryLevel,
            'is_mock_location' => $location->isMockLocation,
            'network_type'     => $location->networkType,
            'last_location_at' => $location->timestamp,
            'updated_at'       => Carbon::now(),
        ];

        try {
            VehicleCurrentState::updateOrCreate(
                ['vehicle_id' => $location->vehicleId],
                $stateAttributes
            );
        } catch (\Throwable $e) {
            Log::warning("Could not update vehicle_current_states: {$e->getMessage()}");
        }

        // Cache for ultra-fast parent and school portal retrieval
        Cache::put("gps_vehicle_state_{$location->vehicleId}", $location, 300);
    }

    /**
     * Increment active trip distance and touch GPS session.
     */
    private function updateTripMetrics(GpsTrip $trip, NormalizedLocation $location): void
    {
        $lastPointCacheKey = "gps_trip_last_point_{$trip->id}";
        $lastPoint = Cache::get($lastPointCacheKey);

        if ($lastPoint && isset($lastPoint['lat'], $lastPoint['lng'])) {
            $deltaKm = $this->validator->calculateDistanceKm(
                (float) $lastPoint['lat'],
                (float) $lastPoint['lng'],
                $location->latitude,
                $location->longitude
            );

            // Ignore jitter < 15 meters
            if ($deltaKm >= 0.015 && $deltaKm < 2.0) {
                $trip->increment('distance_km', round($deltaKm, 3));
            }
        }

        Cache::put($lastPointCacheKey, [
            'lat' => $location->latitude,
            'lng' => $location->longitude,
            'time' => Carbon::now()->timestamp,
        ], 3600);

        // Touch active GPS session
        GpsSession::where('trip_id', $trip->id)
            ->where('status', 'ACTIVE')
            ->update([
                'last_location_at' => $location->timestamp,
                'updated_at' => Carbon::now(),
            ]);
    }
}
