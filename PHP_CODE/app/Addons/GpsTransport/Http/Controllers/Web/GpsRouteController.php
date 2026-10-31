<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsRouteGeometry;
use App\Http\Controllers\Controller;
use App\Models\PickupPoint;
use App\Models\Route;
use App\Models\RoutePickupPoint;
use App\Models\TransportationPayment;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * GpsRouteController
 *
 * Route builder: stops placed on the map, per-stop geofence / halt time / landmark,
 * separate morning (pickup) and afternoon (drop) stop orders, and the route polyline
 * used by route-deviation monitoring.
 */
class GpsRouteController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['route-list', 'pickup-points-list']);

        $routes = Route::with(['routePickupPoints.pickupPoint', 'shift'])->get();
        $pickupPoints = PickupPoint::where('status', 1)->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']);

        return view('gpstransport::route-builder', compact('routes', 'pickupPoints'));
    }

    /**
     * Route stops and geometry for the map editor.
     */
    public function getRouteData($routeId): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['route-list', 'pickup-points-list']);

        $route = Route::find($routeId);
        if (!$route) {
            return response()->json(['error' => true, 'message' => 'Route not found'], 404);
        }

        $geometry = GpsRouteGeometry::where('route_id', $routeId)->where('active', 1)->first();
        $morning = RoutePickupPoint::forTrip($route->id, 'MORNING', true)->pluck('id')->values();
        $afternoon = RoutePickupPoint::forTrip($route->id, 'EVENING', true)->pluck('id')->values();

        $stops = RoutePickupPoint::with('pickupPoint')->where('route_id', $route->id)->get()
            ->map(function (RoutePickupPoint $rp) use ($morning, $afternoon) {
                $p = $rp->pickupPoint;
                $m = $morning->search($rp->id);
                $a = $afternoon->search($rp->id);
                return [
                    'route_stop_id'         => $rp->id,
                    'pickup_point_id'       => $rp->pickup_point_id,
                    'name'                  => $p?->name ?? 'Stop',
                    'latitude'              => $p?->latitude !== null ? (float) $p->latitude : null,
                    'longitude'             => $p?->longitude !== null ? (float) $p->longitude : null,
                    'geofence_radius'       => (float) ($p?->geofence_radius ?? 100),
                    'halt_duration_minutes' => (int) ($p?->halt_duration_minutes ?? 2),
                    'landmark'              => $p?->landmark,
                    'contact_person'        => $p?->contact_person,
                    'contact_phone'         => $p?->contact_phone,
                    'pickup_time'           => $rp->pickup_time ? substr($rp->pickup_time, 0, 5) : null,
                    'drop_time'             => $rp->drop_time ? substr($rp->drop_time, 0, 5) : null,
                    'is_active'             => $rp->is_active !== false,
                    'morning_order'         => $m === false ? null : $m + 1,
                    'afternoon_order'       => $a === false ? null : $a + 1,
                ];
            })
            ->sortBy(fn ($s) => $s['morning_order'] ?? PHP_INT_MAX)
            ->values();

        return response()->json([
            'error'  => false,
            'route'  => [
                'id'             => $route->id,
                'name'           => $route->name,
                'distance'       => $route->distance,
                'start_landmark' => $route->start_landmark,
                'end_landmark'   => $route->end_landmark,
            ],
            'stops'    => $stops,
            'geometry' => $geometry ? ($geometry->waypoints_json ?? []) : [],
        ]);
    }

    /**
     * Save the whole route in one go: stop details, both stop orders, removed stops and
     * the polyline. New stops placed on the map become pickup points.
     */
    public function saveRoute(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['route-edit', 'route-create']);

        $validator = Validator::make($request->all(), [
            'route_id'                      => 'required|integer|exists:routes,id',
            'start_landmark'                => 'nullable|string|max:255',
            'end_landmark'                  => 'nullable|string|max:255',
            'stops'                         => 'present|array',
            'stops.*.route_stop_id'         => 'nullable|integer',
            'stops.*.pickup_point_id'       => 'nullable|integer|exists:pickup_points,id',
            'stops.*.name'                  => 'required|string|max:191',
            'stops.*.latitude'              => 'required|numeric|between:-90,90',
            'stops.*.longitude'             => 'required|numeric|between:-180,180',
            'stops.*.geofence_radius'       => 'required|numeric|min:20|max:1000',
            'stops.*.halt_duration_minutes' => 'required|integer|min:0|max:60',
            'stops.*.landmark'              => 'nullable|string|max:255',
            'stops.*.contact_person'        => 'nullable|string|max:191',
            'stops.*.contact_phone'         => 'nullable|string|max:30',
            'stops.*.pickup_time'           => 'nullable|date_format:H:i',
            'stops.*.drop_time'             => 'nullable|date_format:H:i',
            'stops.*.is_active'             => 'boolean',
            'stops.*.morning_order'         => 'required|integer|min:1',
            'stops.*.afternoon_order'       => 'required|integer|min:1',
            'waypoints'                     => 'nullable|array',
            'waypoints.*.lat'               => 'required_with:waypoints|numeric|between:-90,90',
            'waypoints.*.lng'               => 'required_with:waypoints|numeric|between:-180,180',
            'total_distance'                => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $routeId = (int) $request->route_id;
        $stops = collect($request->stops);

        $keptIds = $stops->pluck('route_stop_id')->filter()->map(fn ($id) => (int) $id)->all();
        $removed = RoutePickupPoint::where('route_id', $routeId)->whereNotIn('id', $keptIds)->get();

        // A stop that students are booked on cannot silently disappear from the route.
        $booked = TransportationPayment::whereIn('pickup_point_id', $removed->pluck('pickup_point_id'))
            ->whereHas('routeVehicle', fn ($q) => $q->where('route_id', $routeId))
            ->where('expiry_date', '>=', Carbon::today())
            ->pluck('pickup_point_id')
            ->unique();
        if ($booked->isNotEmpty()) {
            $names = PickupPoint::whereIn('id', $booked)->pluck('name')->implode(', ');
            return response()->json([
                'error'   => true,
                'message' => "Students are booked on these stops, so they cannot be removed (mark them inactive instead): {$names}",
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($stops as $s) {
                $pointData = [
                    'name'                  => $s['name'],
                    'latitude'              => $s['latitude'],
                    'longitude'             => $s['longitude'],
                    'geofence_radius'       => $s['geofence_radius'],
                    'halt_duration_minutes' => $s['halt_duration_minutes'],
                    'landmark'              => $s['landmark'] ?? null,
                    'contact_person'        => $s['contact_person'] ?? null,
                    'contact_phone'         => $s['contact_phone'] ?? null,
                ];
                if (!empty($s['pickup_point_id'])) {
                    $point = PickupPoint::find($s['pickup_point_id']);
                    $point->update($pointData);
                } else {
                    $point = PickupPoint::create($pointData + ['status' => 1]);
                }

                $stopData = [
                    'route_id'        => $routeId,
                    'pickup_point_id' => $point->id,
                    'pickup_time'     => $s['pickup_time'] ?? null,
                    'drop_time'       => $s['drop_time'] ?? null,
                    'order'           => (int) $s['morning_order'],
                    'morning_order'   => (int) $s['morning_order'],
                    'afternoon_order' => (int) $s['afternoon_order'],
                    'is_active'       => (bool) ($s['is_active'] ?? true),
                ];

                $existing = !empty($s['route_stop_id'])
                    ? RoutePickupPoint::where('route_id', $routeId)->find($s['route_stop_id'])
                    : RoutePickupPoint::where('route_id', $routeId)->where('pickup_point_id', $point->id)->first();
                $existing ? $existing->update($stopData) : RoutePickupPoint::create($stopData);
            }

            RoutePickupPoint::whereIn('id', $removed->pluck('id'))->delete();

            $distance = (float) ($request->total_distance ?? 0);
            if (is_array($request->waypoints)) {
                GpsRouteGeometry::updateOrCreate(
                    ['route_id' => $routeId],
                    ['waypoints_json' => $request->waypoints, 'total_distance_km' => $distance, 'active' => true]
                );
            }

            Route::where('id', $routeId)->update(array_filter([
                'distance'       => $distance > 0 ? $distance : null,
                'start_landmark' => $request->start_landmark,
                'end_landmark'   => $request->end_landmark,
            ], fn ($v) => $v !== null));

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'GpsRouteController -> saveRoute', 'Error Occurred', false);
            return response()->json(['error' => true, 'message' => 'Could not save the route.'], 500);
        }

        return response()->json(['error' => false, 'message' => 'Route saved']);
    }

    /**
     * Save only the route polyline (kept for older clients of this endpoint).
     */
    public function saveGeometry(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['route-edit', 'route-create']);

        $request->validate([
            'route_id'       => 'required|integer|exists:routes,id',
            'waypoints'      => 'required|array',
            'total_distance' => 'nullable|numeric',
        ]);

        $distance = (float) ($request->input('total_distance') ?? 0.0);
        GpsRouteGeometry::updateOrCreate(
            ['route_id' => $request->input('route_id')],
            ['waypoints_json' => $request->input('waypoints'), 'total_distance_km' => $distance, 'active' => true]
        );
        Route::where('id', $request->input('route_id'))->update(['distance' => $distance]);

        return response()->json(['error' => false, 'message' => 'Route geometry saved successfully']);
    }
}
