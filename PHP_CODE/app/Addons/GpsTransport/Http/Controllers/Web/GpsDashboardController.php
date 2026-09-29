<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsAlert;
use App\Addons\GpsTransport\Models\GpsEmergencyIncident;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GpsDashboardController
 *
 * Implements Section 53: Transport Dashboard & Overview.
 */
class GpsDashboardController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $totalVehicles = Vehicle::count();
        $activeTrips = GpsTrip::where('status', 'ACTIVE')->count();
        $completedToday = GpsTrip::where('status', 'COMPLETED')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        // Calculate dynamic status counts
        $currentStates = VehicleCurrentState::with('vehicle')->get();
        $staleThreshold = config('gpstransport.defaults.stale_threshold_seconds', 300);
        $now = Carbon::now();

        $movingCount = 0;
        $stoppedCount = 0;
        $offlineCount = 0;

        foreach ($currentStates as $state) {
            $isFresh = $state->last_location_at && $now->diffInSeconds($state->last_location_at) <= $staleThreshold;
            if (!$isFresh) {
                $offlineCount++;
            } elseif ($state->speed >= 3.0) {
                $movingCount++;
            } else {
                $stoppedCount++;
            }
        }

        $activeEmergencies = GpsEmergencyIncident::whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count();
        $recentAlerts = GpsAlert::with('vehicle')->latest()->take(5)->get();
        $activeTripsList = GpsTrip::with(['vehicle', 'driver', 'route'])
            ->where('status', 'ACTIVE')
            ->get();

        return view('gpstransport::dashboard', compact(
            'totalVehicles',
            'activeTrips',
            'completedToday',
            'movingCount',
            'stoppedCount',
            'offlineCount',
            'activeEmergencies',
            'recentAlerts',
            'activeTripsList'
        ));
    }

    /**
     * Real-time JSON summary for background dashboard polling.
     */
    public function liveStats(): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $states = VehicleCurrentState::with(['vehicle', 'trip.route', 'driver'])->get();
        $staleThreshold = config('gpstransport.defaults.stale_threshold_seconds', 300);
        $now = Carbon::now();

        $fleet = $states->map(function ($state) use ($now, $staleThreshold) {
            $isOnline = $state->last_location_at && $now->diffInSeconds($state->last_location_at) <= $staleThreshold;
            return [
                'vehicle_id'     => $state->vehicle_id,
                'vehicle_number' => $state->vehicle?->vehicle_number ?? "Bus #{$state->vehicle_id}",
                'latitude'       => (float) $state->latitude,
                'longitude'      => (float) $state->longitude,
                'speed'          => (float) $state->speed,
                'heading'        => (float) $state->heading,
                'status'         => $isOnline ? ($state->speed >= 3 ? 'MOVING' : 'STOPPED') : 'OFFLINE',
                'is_online'      => $isOnline,
                'route_name'     => $state->trip?->route?->name ?? 'Unassigned',
                'driver_name'    => $state->driver?->name ?? 'Unassigned',
                'eta'            => $state->eta ?? 'N/A',
                'battery_level'  => $state->battery_level,
                'last_seen'      => $state->last_location_at ? $state->last_location_at->diffForHumans() : 'Never',
            ];
        });

        return response()->json([
            'error' => false,
            'fleet' => $fleet,
            'summary' => [
                'active_trips'       => GpsTrip::where('status', 'ACTIVE')->count(),
                'active_emergencies' => GpsEmergencyIncident::whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count(),
                'moving'             => $fleet->where('status', 'MOVING')->count(),
                'stopped'            => $fleet->where('status', 'STOPPED')->count(),
                'offline'            => $fleet->where('status', 'OFFLINE')->count(),
            ],
        ]);
    }
}
