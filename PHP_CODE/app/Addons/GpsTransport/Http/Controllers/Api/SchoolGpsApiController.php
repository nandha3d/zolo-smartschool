<?php

namespace App\Addons\GpsTransport\Http\Controllers\Api;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Addons\GpsTransport\Models\VehicleLocation;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SchoolGpsApiController
 *
 * Real-time fleet endpoints for school portal live tracking and web dashboard map.
 */
class SchoolGpsApiController extends Controller
{
    /**
     * Get live telemetry for all fleet vehicles with active status, heading, speed, next stop, and ETA.
     */
    public function fleetLive(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $routeId = $request->get('route_id');
        $query = VehicleCurrentState::with(['vehicle', 'trip.route', 'trip.driver', 'nextStop']);

        if ($routeId) {
            $query->whereHas('trip', fn($q) => $q->where('route_id', $routeId));
        }

        $states = $query->get();
        $staleThreshold = config('gpstransport.defaults.stale_threshold_seconds', 300);
        $now = Carbon::now();

        $fleet = $states->map(function ($state) use ($now, $staleThreshold) {
            $isFresh = $state->last_location_at && $now->diffInSeconds($state->last_location_at) <= $staleThreshold;
            $speed = (float) $state->speed;

            $status = $state->status;
            if (!$isFresh) {
                $status = 'OFFLINE';
            } elseif ($status !== 'EMERGENCY' && $status !== 'BREAKDOWN') {
                $status = ($speed >= 3.0) ? 'MOVING' : 'STOPPED';
            }

            return [
                'vehicle_id'     => $state->vehicle_id,
                'vehicle_number' => $state->vehicle?->vehicle_number ?? "Bus #{$state->vehicle_id}",
                'registration'   => $state->vehicle?->registration_number ?? '',
                'latitude'       => (float) $state->latitude,
                'longitude'      => (float) $state->longitude,
                'speed'          => $speed,
                'heading'        => (float) $state->heading,
                'status'         => $status,
                'gps_status'     => $isFresh ? 'ONLINE' : 'STALE',
                'route_id'       => $state->trip?->route_id,
                'route_name'     => $state->trip?->route?->name ?? 'Unassigned',
                'driver_name'    => $state->trip?->driver?->name ?? 'Unassigned',
                'driver_phone'   => $state->trip?->driver?->phone ?? '',
                'next_stop'      => $state->nextStop?->name ?? 'In Transit',
                'eta'            => $state->eta ?? 'N/A',
                'battery_level'  => $state->battery_level,
                'trip_id'        => $state->trip_id,
                'last_update'    => $state->last_location_at ? $state->last_location_at->diffForHumans() : 'Never',
                'updated_at'     => $state->last_location_at ? $state->last_location_at->toIso8601String() : null,
            ];
        });

        return response()->json([
            'error'     => false,
            'fleet'     => $fleet,
            'timestamp' => $now->toIso8601String(),
        ]);
    }

    /**
     * Get detailed telemetry and trail for an individual active trip.
     */
    public function tripTelemetry($tripId): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $trip = GpsTrip::with([
            'vehicle',
            'driver',
            'route.routePickupPoints.pickupPoint',
            'tripStops.stop',
        ])->findOrFail($tripId);

        $trail = VehicleLocation::where('trip_id', $tripId)
            ->latest('timestamp')
            ->take(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($loc) => [
                'lat'       => (float) $loc->latitude,
                'lng'       => (float) $loc->longitude,
                'speed'     => (float) $loc->speed,
                'heading'   => (float) $loc->heading,
                'timestamp' => $loc->timestamp->format('H:i:s'),
            ]);

        $currentState = VehicleCurrentState::find($trip->vehicle_id);

        return response()->json([
            'error'          => false,
            'trip_id'        => $trip->id,
            'trip_number'    => $trip->trip_number,
            'vehicle_number' => $trip->vehicle?->vehicle_number ?? 'School Bus',
            'driver_name'    => $trip->driver?->name ?? 'Driver',
            'current'        => [
                'latitude'  => (float) ($currentState?->latitude ?? 0.0),
                'longitude' => (float) ($currentState?->longitude ?? 0.0),
                'speed'     => (float) ($currentState?->speed ?? 0.0),
                'heading'   => (float) ($currentState?->heading ?? 0.0),
                'status'    => $currentState?->status ?? 'MOVING',
                'eta'       => $currentState?->eta ?? 'N/A',
            ],
            'trail'          => $trail,
            'stops'          => $trip->tripStops->map(fn($ts) => [
                'name'         => $ts->stop?->name ?? 'Stop',
                'lat'          => (float) ($ts->stop?->latitude ?? 0.0),
                'lng'          => (float) ($ts->stop?->longitude ?? 0.0),
                'status'       => $ts->status,
                'planned_time' => $ts->planned_arrival_time,
            ]),
        ]);
    }
}
