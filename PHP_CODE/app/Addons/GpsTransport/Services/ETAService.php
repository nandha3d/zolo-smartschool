<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\GpsTripStop;
use Carbon\Carbon;

/**
 * ETAService
 *
 * Implements dynamic ETA estimation and Delay Detection (Sections 37 & 38).
 * Accounts for remaining route distance, current travel speed, and stop dwell time.
 */
class ETAService
{
    private LocationValidator $distanceCalculator;

    public function __construct(LocationValidator $distanceCalculator)
    {
        $this->distanceCalculator = $distanceCalculator;
    }

    /**
     * Calculate ETAs for remaining stops and destination of an active trip.
     *
     * @param NormalizedLocation $location
     * @param GpsTrip $trip
     * @return array [ 'next_stop' => [...], 'stops' => [...], 'destination_eta' => ?string, 'delay_minutes' => int ]
     */
    public function calculate(NormalizedLocation $location, GpsTrip $trip): array
    {
        $remainingStops = GpsTripStop::with('stop')
            ->where('trip_id', $trip->id)
            ->whereIn('status', ['UPCOMING', 'APPROACHING', 'ARRIVED', 'WAITING'])
            ->orderBy('sequence')
            ->get();

        if ($remainingStops->isEmpty()) {
            return [
                'next_stop'       => null,
                'stops'           => [],
                'destination_eta' => null,
                'delay_minutes'   => 0,
            ];
        }

        // Determine effective speed (km/h) for ETA:
        // Use current speed if >= 10 km/h, otherwise default to urban transit speed (25 km/h)
        $effectiveSpeedKmh = ($location->speed >= 10.0) ? min(70.0, $location->speed) : 25.0;

        $currentTime = Carbon::now();
        $runningTime = $currentTime->copy();
        $lastLat = $location->latitude;
        $lastLng = $location->longitude;
        $stopETAs = [];
        $totalDelayMinutes = 0;
        $dwellTimeMinutesPerStop = 2; // Average boarding dwell time

        foreach ($remainingStops as $index => $tripStop) {
            $stop = $tripStop->stop;
            if (!$stop || !$stop->latitude || !$stop->longitude) {
                continue;
            }

            // Segment distance from current location (or previous stop)
            $segmentDistanceKm = $this->distanceCalculator->calculateDistanceKm(
                $lastLat,
                $lastLng,
                (float) $stop->latitude,
                (float) $stop->longitude
            );

            // Travel duration = (distance / speed) in hours -> convert to minutes
            $travelMinutes = max(1, (int) round(($segmentDistanceKm / $effectiveSpeedKmh) * 60));
            $runningTime->addMinutes($travelMinutes);

            $estimatedArrival = $runningTime->copy();

            // Compare with planned arrival time if available
            $delayMinutes = 0;
            if ($tripStop->planned_arrival_time) {
                try {
                    $plannedTime = Carbon::createFromFormat('H:i:s', $tripStop->planned_arrival_time);
                    $plannedToday = $currentTime->copy()->setTime($plannedTime->hour, $plannedTime->minute, $plannedTime->second);
                    if ($estimatedArrival->greaterThan($plannedToday)) {
                        $delayMinutes = $estimatedArrival->diffInMinutes($plannedToday);
                    }
                } catch (\Throwable) {
                    // Ignore format mismatch
                }
            }

            $stopETAs[] = [
                'trip_stop_id'         => $tripStop->id,
                'stop_id'              => $stop->id,
                'stop_name'            => $stop->name,
                'sequence'             => $tripStop->sequence,
                'distance_km'          => round($segmentDistanceKm, 2),
                'eta_time'             => $estimatedArrival->format('h:i A'),
                'eta_iso'              => $estimatedArrival->toIso8601String(),
                'minutes_from_now'     => $currentTime->diffInMinutes($estimatedArrival),
                'planned_arrival_time' => $tripStop->planned_arrival_time,
                'delay_minutes'        => $delayMinutes,
            ];

            // Add dwell time before calculating next stop
            $runningTime->addMinutes($dwellTimeMinutesPerStop);
            $lastLat = (float) $stop->latitude;
            $lastLng = (float) $stop->longitude;
            $totalDelayMinutes = max($totalDelayMinutes, $delayMinutes);
        }

        $nextStop = $stopETAs[0] ?? null;
        $finalStop = end($stopETAs) ?: null;

        return [
            'next_stop'       => $nextStop,
            'stops'           => $stopETAs,
            'destination_eta' => $finalStop ? $finalStop['eta_time'] : null,
            'delay_minutes'   => $totalDelayMinutes,
        ];
    }
}
