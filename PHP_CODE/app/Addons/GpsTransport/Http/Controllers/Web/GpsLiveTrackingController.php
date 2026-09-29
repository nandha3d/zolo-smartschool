<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Vehicle;
use App\Services\ResponseService;
use Illuminate\Http\Request;

/**
 * GpsLiveTrackingController
 *
 * Implements Section 20 & 53: Live Map & Fleet Tracking Portal.
 */
class GpsLiveTrackingController extends Controller
{
    public function index()
    {
        $request = request();
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $vehicles = Vehicle::with(['routeVehicles.route', 'routeVehicles.driver'])->get();
        $routes = Route::where('status', 1)->get();
        $selectedVehicleId = $request->get('vehicle_id');

        $activeTrips = GpsTrip::with(['vehicle', 'driver', 'route', 'tripStops.stop'])
            ->where('status', 'ACTIVE')
            ->get();

        return view('gpstransport::live-tracking', compact(
            'vehicles',
            'routes',
            'activeTrips',
            'selectedVehicleId'
        ));
    }
}
