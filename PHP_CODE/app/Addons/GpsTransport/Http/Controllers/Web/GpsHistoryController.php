<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleLocation;
use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Vehicle;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GpsHistoryController
 *
 * Implements Sections 51 & 52: Trip History & Trip Replay.
 */
class GpsHistoryController extends Controller
{
    public function index()
    {
        $request = request();
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $vehicleId = $request->get('vehicle_id');
        $routeId = $request->get('route_id');
        $date = $request->get('date', Carbon::today()->toDateString());

        $query = GpsTrip::with(['vehicle', 'driver', 'route'])
            ->where('status', 'COMPLETED')
            ->latest('actual_start_time');

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }
        if ($routeId) {
            $query->where('route_id', $routeId);
        }
        if ($date) {
            $query->whereDate('actual_start_time', $date);
        }

        $trips = $query->paginate(15);
        $vehicles = Vehicle::all();
        $routes = Route::all();

        return view('gpstransport::trip-replay', compact('trips', 'vehicles', 'routes', 'date', 'vehicleId', 'routeId'));
    }

    /**
     * Fetch complete telemetry breadcrumbs for interactive trip playback.
     */
    public function replayData($tripId): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $trip = GpsTrip::with([
            'vehicle',
            'driver',
            'route.routePickupPoints.pickupPoint',
            'tripStops.stop',
            'boardingEvents.student.user',
            'geofenceEvents',
            'alerts',
        ])->findOrFail($tripId);

        $locations = VehicleLocation::where('trip_id', $tripId)
            ->orderBy('timestamp')
            ->get()
            ->map(function ($loc) {
                return [
                    'lat'       => (float) $loc->latitude,
                    'lng'       => (float) $loc->longitude,
                    'speed'     => (float) $loc->speed,
                    'heading'   => (float) $loc->heading,
                    'accuracy'  => (float) $loc->accuracy,
                    'timestamp' => $loc->timestamp->format('H:i:s'),
                    'battery'   => $loc->battery_level,
                ];
            });

        $stops = $trip->tripStops->map(function ($ts) {
            return [
                'name'         => $ts->stop?->name ?? 'Stop',
                'lat'          => (float) ($ts->stop?->latitude ?? 0.0),
                'lng'          => (float) ($ts->stop?->longitude ?? 0.0),
                'planned_time' => $ts->planned_arrival_time,
                'actual_time'  => $ts->actual_arrival_time,
                'status'       => $ts->status,
                'boarded'      => $ts->actual_board,
                'dropped'      => $ts->actual_drop,
            ];
        });

        $boardingEvents = $trip->boardingEvents->map(function ($ev) {
            return [
                'type'      => $ev->event_type,
                'student'   => $ev->student?->user?->first_name ?? "Student #{$ev->student_id}",
                'lat'       => (float) $ev->latitude,
                'lng'       => (float) $ev->longitude,
                'time'      => $ev->timestamp->format('H:i:s'),
                'notes'     => $ev->notes,
            ];
        });

        return response()->json([
            'error'     => false,
            'trip'      => [
                'id'             => $trip->id,
                'number'         => $trip->trip_number,
                'vehicle_number' => $trip->vehicle?->vehicle_number ?? "Bus #{$trip->vehicle_id}",
                'driver_name'    => $trip->driver?->name ?? 'Unassigned',
                'route_name'     => $trip->route?->name ?? 'Unassigned',
                'distance_km'    => $trip->distance_km,
                'duration_min'   => $trip->duration_minutes,
                'start_time'     => $trip->actual_start_time ? $trip->actual_start_time->format('h:i A') : null,
                'end_time'       => $trip->actual_end_time ? $trip->actual_end_time->format('h:i A') : null,
            ],
            'locations'       => $locations,
            'stops'           => $stops,
            'boarding_events' => $boardingEvents,
        ]);
    }
}
