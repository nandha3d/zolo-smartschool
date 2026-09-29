<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsGeofence;
use App\Addons\GpsTransport\Models\GpsGeofenceEvent;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\GpsTripStop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * GeofenceService
 *
 * Implements Geofencing, Geofence Events, and Stop State Machine (Sections 27, 28 & 29).
 * Evaluates stops, school perimeter, and custom zones.
 */
class GeofenceService
{
    private LocationValidator $distanceCalculator;
    private GpsNotificationService $notificationService;

    public function __construct(
        LocationValidator $distanceCalculator,
        GpsNotificationService $notificationService
    ) {
        $this->distanceCalculator = $distanceCalculator;
        $this->notificationService = $notificationService;
    }

    /**
     * Evaluate geofence interactions for an active trip.
     *
     * @param NormalizedLocation $location
     * @param GpsTrip|null $trip
     * @return array Events triggered during this update
     */
    public function evaluate(NormalizedLocation $location, ?GpsTrip $trip): array
    {
        $triggeredEvents = [];

        if (!$trip) {
            return $triggeredEvents;
        }

        $now = Carbon::now();
        $approachingThresholdMeters = config('gpstransport.defaults.approaching_radius_meters', 500);
        $defaultStopRadiusMeters = config('gpstransport.defaults.default_stop_radius_meters', 100);

        // 1. Evaluate trip stops
        $tripStops = GpsTripStop::with('stop')
            ->where('trip_id', $trip->id)
            ->orderBy('sequence')
            ->get();

        foreach ($tripStops as $tripStop) {
            $stop = $tripStop->stop;
            if (!$stop || !$stop->latitude || !$stop->longitude) {
                continue;
            }

            $stopRadius = $stop->geofence_radius ?: $defaultStopRadiusMeters;
            $distance = $this->distanceCalculator->calculateDistanceMeters(
                $location->latitude,
                $location->longitude,
                (float) $stop->latitude,
                (float) $stop->longitude
            );

            $currentState = $tripStop->status; // UPCOMING, APPROACHING, ARRIVED, WAITING, DEPARTED, SKIPPED

            // Case A: Bus is approaching stop (< 500m)
            if ($distance <= $approachingThresholdMeters && $distance > $stopRadius) {
                if ($currentState === 'UPCOMING') {
                    $tripStop->status = 'APPROACHING';
                    $tripStop->save();

                    $this->recordGeofenceEvent($trip, $location, 'STOP_APPROACHING', "Approaching stop: {$stop->name} ({$distance}m)");
                    $this->notificationService->sendStopApproachingNotification($trip, $tripStop, (int) round($distance));
                    $triggeredEvents[] = [
                        'type' => 'STOP_APPROACHING',
                        'stop_id' => $stop->id,
                        'stop_name' => $stop->name,
                        'distance' => round($distance),
                    ];
                }
            }

            // Case B: Bus arrived inside stop radius (< 100m)
            elseif ($distance <= $stopRadius) {
                if (in_array($currentState, ['UPCOMING', 'APPROACHING'])) {
                    $tripStop->status = 'ARRIVED';
                    $tripStop->actual_arrival_time = $now->toTimeString();
                    $tripStop->save();

                    $this->recordGeofenceEvent($trip, $location, 'STOP_ARRIVED', "Arrived at stop: {$stop->name}");
                    $this->notificationService->sendStopArrivedNotification($trip, $tripStop);
                    $triggeredEvents[] = [
                        'type' => 'STOP_ARRIVED',
                        'stop_id' => $stop->id,
                        'stop_name' => $stop->name,
                    ];
                }
            }

            // Case C: Bus has departed stop (> radius after being ARRIVED/WAITING)
            elseif ($distance > $stopRadius && in_array($currentState, ['ARRIVED', 'WAITING'])) {
                $tripStop->status = 'DEPARTED';
                $tripStop->actual_departure_time = $now->toTimeString();
                $tripStop->save();

                $this->recordGeofenceEvent($trip, $location, 'STOP_DEPARTED', "Departed stop: {$stop->name}");
                $triggeredEvents[] = [
                    'type' => 'STOP_DEPARTED',
                    'stop_id' => $stop->id,
                    'stop_name' => $stop->name,
                ];
            }
        }

        // 2. Evaluate School / Custom Geofences
        $customGeofences = GpsGeofence::where('active', 1)->get();
        foreach ($customGeofences as $geofence) {
            $dist = $this->distanceCalculator->calculateDistanceMeters(
                $location->latitude,
                $location->longitude,
                (float) $geofence->latitude,
                (float) $geofence->longitude
            );

            $radius = $geofence->radius_meters ?: 200;
            $isInside = ($dist <= $radius);

            // Check previous state
            $lastEvent = GpsGeofenceEvent::where('trip_id', $trip->id)
                ->where('geofence_id', $geofence->id)
                ->latest()
                ->first();

            $wasInside = $lastEvent && in_array($lastEvent->event_type, ['GEOFENCE_ENTER', 'SCHOOL_ENTERED']);

            if ($isInside && !$wasInside) {
                $eventType = ($geofence->type === 'SCHOOL') ? 'SCHOOL_ENTERED' : 'GEOFENCE_ENTER';
                $this->recordGeofenceEvent($trip, $location, $eventType, "Entered geofence: {$geofence->name}", $geofence->id);
                $triggeredEvents[] = ['type' => $eventType, 'geofence' => $geofence->name];
            } elseif (!$isInside && $wasInside) {
                $eventType = ($geofence->type === 'SCHOOL') ? 'SCHOOL_EXITED' : 'GEOFENCE_EXIT';
                $this->recordGeofenceEvent($trip, $location, $eventType, "Exited geofence: {$geofence->name}", $geofence->id);
                $triggeredEvents[] = ['type' => $eventType, 'geofence' => $geofence->name];
            }
        }

        return $triggeredEvents;
    }

    private function recordGeofenceEvent(
        GpsTrip $trip,
        NormalizedLocation $location,
        string $eventType,
        string $description,
        ?int $geofenceId = null
    ): void {
        try {
            GpsGeofenceEvent::create([
                'trip_id'      => $trip->id,
                'vehicle_id'   => $trip->vehicle_id,
                'geofence_id'  => $geofenceId,
                'event_type'   => $eventType,
                'latitude'     => $location->latitude,
                'longitude'    => $location->longitude,
                'speed'        => $location->speed,
                'timestamp'    => $location->timestamp,
                'description'  => $description,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Could not record geofence event: {$e->getMessage()}");
        }
    }
}
