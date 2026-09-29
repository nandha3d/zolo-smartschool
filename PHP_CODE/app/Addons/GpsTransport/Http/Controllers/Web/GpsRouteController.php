<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsRouteGeometry;
use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
use App\Models\Route;
use App\Models\RoutePickupPoint;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GpsRouteController
 *
 * Implements Section 23-26: Route Builder, Stop Geofences, and Route Polyline.
 */
class GpsRouteController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['route-list', 'pickup-points-list']);

        $routes = Route::with(['routePickupPoints.pickupPoint', 'shift'])->get();
        $pickupPoints = PickupPoint::where('status', 1)->get();

        return view('gpstransport::route-builder', compact('routes', 'pickupPoints'));
    }

    /**
     * Get route stops and geometry data for the interactive map editor.
     */
    public function getRouteData($routeId): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        try {
            $route = Route::with(['routePickupPoints.pickupPoint'])->findOrFail($routeId);
            $geometry = GpsRouteGeometry::where('route_id', $routeId)->where('active', 1)->first();

            $stops = $route->routePickupPoints->map(function ($rp) {
                return [
                    'id'              => $rp->id,
                    'stop_id'         => $rp->pickup_point_id,
                    'name'            => $rp->pickupPoint?->name ?? 'Stop',
                    'latitude'        => (float) ($rp->pickupPoint?->latitude ?? 0.0),
                    'longitude'       => (float) ($rp->pickupPoint?->longitude ?? 0.0),
                    'geofence_radius' => (float) ($rp->pickupPoint?->geofence_radius ?? 100.0),
                    'order'           => $rp->order,
                    'pickup_time'     => $rp->pickup_time,
                    'drop_time'       => $rp->drop_time,
                ];
            });

            return response()->json([
                'error' => false,
                'route' => [
                    'id'       => $route->id,
                    'name'     => $route->name,
                    'distance' => $route->distance,
                ],
                'stops'     => $stops,
                'geometry'  => $geometry ? $geometry->waypoints_json : [],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Save route geometry polyline and updated stop waypoints.
     */
    public function saveGeometry(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['route-edit', 'route-create']);

        $request->validate([
            'route_id'       => 'required|integer',
            'waypoints'      => 'required|array',
            'total_distance' => 'nullable|numeric',
        ]);

        try {
            $routeId = $request->input('route_id');
            $waypoints = $request->input('waypoints');
            $distance = (float) ($request->input('total_distance') ?? 0.0);

            GpsRouteGeometry::updateOrCreate(
                ['route_id' => $routeId],
                [
                    'waypoints_json'    => $waypoints,
                    'total_distance_km' => $distance,
                    'active'            => true,
                ]
            );

            // Also update route distance
            Route::where('id', $routeId)->update(['distance' => $distance]);

            return response()->json([
                'error'   => false,
                'message' => 'Route geometry and stops saved successfully',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
