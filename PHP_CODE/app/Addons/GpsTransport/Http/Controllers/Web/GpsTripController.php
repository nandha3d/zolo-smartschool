<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsSession;
use App\Addons\GpsTransport\Models\GpsStudentTransportAssignment;
use App\Addons\GpsTransport\Models\GpsStudentTripAttendance;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\GpsTripStop;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\RoutePickupPoint;
use App\Models\RouteVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GpsTripController
 *
 * Implements Sections 14-16, 48 & 50: Trip Management & Child Still Onboard Safety Check.
 */
class GpsTripController extends Controller
{
    public function index()
    {
        $request = request();
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $statusFilter = $request->get('status');
        $query = GpsTrip::with(['vehicle', 'driver', 'route', 'tripStops.stop'])->latest();

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $trips = $query->paginate(15);
        $vehicles = Vehicle::where('status', 1)->get();
        $routes = Route::where('status', 1)->with('routePickupPoints.pickupPoint')->get();
        $drivers = User::role('Driver')->get();

        return view('gpstransport::trips', compact('trips', 'vehicles', 'routes', 'drivers', 'statusFilter'));
    }

    /**
     * Create / Schedule a new trip.
     */
    public function store(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-create', 'RouteVehicle-edit']);

        $request->validate([
            'vehicle_id'           => 'required|integer|exists:vehicles,id',
            'driver_id'            => 'nullable|integer',
            'route_id'             => 'required|integer|exists:routes,id',
            'trip_type'            => 'required|in:MORNING,EVENING,SPECIAL',
            'scheduled_start_time' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $tripNumber = 'TRP-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $scheduled = $request->scheduled_start_time ? Carbon::parse($request->scheduled_start_time) : Carbon::now();
            $run = match ($request->trip_type) {
                'MORNING' => 'pickup',
                'EVENING' => 'drop',
                default   => null,
            };

            // No driver chosen: whoever is rostered on the route vehicle that day, relief
            // driver included.
            $driverId = $request->driver_id;
            if (!$driverId) {
                $driverId = RouteVehicle::where('route_id', $request->route_id)->where('vehicle_id', $request->vehicle_id)
                    ->where('status', 1)->first()?->effectiveDriverId($scheduled, $run);
            }

            $trip = GpsTrip::create([
                'trip_number'          => $tripNumber,
                'trip_type'            => $request->trip_type,
                'vehicle_id'           => $request->vehicle_id,
                'driver_id'            => $driverId,
                'route_id'             => $request->route_id,
                'status'               => 'SCHEDULED',
                'scheduled_start_time' => $scheduled,
            ]);

            // Copy the active stops in the order this run visits them: the morning pickup
            // order, or the afternoon drop order for evening trips.
            $stops = RoutePickupPoint::forTrip((int) $request->route_id, $request->trip_type);
            foreach ($stops as $i => $rp) {
                GpsTripStop::create([
                    'trip_id'              => $trip->id,
                    'stop_id'              => $rp->pickup_point_id,
                    'sequence'             => $i + 1,
                    'planned_arrival_time' => $request->trip_type === 'EVENING'
                        ? ($rp->drop_time ?: $rp->pickup_time)
                        : ($rp->pickup_time ?: $rp->drop_time),
                    'status'               => 'UPCOMING',
                ]);
            }

            // Populate student roster from assignments
            $assignments = GpsStudentTransportAssignment::where('route_id', $request->route_id)
                ->where('status', 1)
                ->where(function ($q) use ($request) {
                    $q->where('trip_type', $request->trip_type)->orWhere('trip_type', 'BOTH');
                })
                ->get();

            foreach ($assignments as $a) {
                GpsStudentTripAttendance::create([
                    'trip_id'        => $trip->id,
                    'student_id'     => $a->student_id,
                    'pickup_stop_id' => $a->pickup_stop_id,
                    'drop_stop_id'   => $a->drop_stop_id,
                    'status'         => 'EXPECTED',
                ]);
            }

            $trip->total_students = $assignments->count();
            $trip->save();

            DB::commit();

            return response()->json([
                'error'   => false,
                'message' => 'Trip created and roster populated successfully',
                'trip_id' => $trip->id,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Start a trip manually or from admin dispatch.
     */
    public function start($id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-create', 'RouteVehicle-edit']);

        try {
            $trip = GpsTrip::findOrFail($id);
            $trip->status = 'ACTIVE';
            $trip->actual_start_time = Carbon::now();
            $trip->save();

            // Create GPS session
            GpsSession::create([
                'trip_id'    => $trip->id,
                'vehicle_id' => $trip->vehicle_id,
                'driver_id'  => $trip->driver_id,
                'started_at' => Carbon::now(),
                'status'     => 'ACTIVE',
            ]);

            // Update Vehicle Current State
            VehicleCurrentState::updateOrCreate(
                ['vehicle_id' => $trip->vehicle_id],
                [
                    'trip_id'    => $trip->id,
                    'driver_id'  => $trip->driver_id,
                    'status'     => 'ON_TRIP',
                    'gps_status' => 'CONNECTED',
                ]
            );

            return response()->json(['error' => false, 'message' => 'Trip activated and GPS tracking started']);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Complete a trip with mandatory Child Still Onboard Safety Check (Section 48).
     */
    public function complete(Request $request, $id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-create', 'RouteVehicle-edit']);

        $trip = GpsTrip::with('studentAttendances.student.user')->findOrFail($id);

        // Section 48: Child Still Onboard Safety Check
        $stillOnboard = $trip->studentAttendances->where('status', 'BOARDED');
        if ($stillOnboard->count() > 0 && !$request->has('override_reason')) {
            $names = $stillOnboard->map(fn($a) => $a->student?->user?->first_name ?? "Student #{$a->student_id}")->join(', ');
            return response()->json([
                'error'                 => true,
                'still_onboard'         => true,
                'still_onboard_count'   => $stillOnboard->count(),
                'students'              => $names,
                'message'               => "SAFETY WARNING: {$stillOnboard->count()} student(s) still registered onboard ({$names}). You must drop them or provide an override reason to complete the trip.",
            ], 422);
        }

        try {
            $now = Carbon::now();
            $trip->status = 'COMPLETED';
            $trip->actual_end_time = $now;

            if ($trip->actual_start_time) {
                $trip->duration_minutes = $trip->actual_start_time->diffInMinutes($now);
            }

            if ($request->has('override_reason')) {
                $trip->notes = ($trip->notes ? $trip->notes . " | " : "") . "Override at completion: " . $request->override_reason;
            }

            $trip->save();

            // End active GPS session
            GpsSession::where('trip_id', $trip->id)
                ->where('status', 'ACTIVE')
                ->update(['ended_at' => $now, 'status' => 'ENDED']);

            // Update Vehicle Current State
            VehicleCurrentState::where('vehicle_id', $trip->vehicle_id)->update([
                'trip_id' => null,
                'status'  => 'STOPPED',
            ]);

            return response()->json([
                'error'   => false,
                'message' => 'Trip safely completed and archived',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
