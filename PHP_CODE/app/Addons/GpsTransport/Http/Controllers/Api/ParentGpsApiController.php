<?php

namespace App\Addons\GpsTransport\Http\Controllers\Api;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsBoardingEvent;
use App\Addons\GpsTransport\Models\GpsStudentTransportAssignment;
use App\Addons\GpsTransport\Models\GpsStudentTripAttendance;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Addons\GpsTransport\Services\ETAService;
use App\Http\Controllers\Controller;
use App\Models\Students;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * ParentGpsApiController
 *
 * Implements Sections 34, 35, 36 & 68: Parent Tracking Portal & Strict Privacy Access Control.
 */
class ParentGpsApiController extends Controller
{
    private ETAService $etaService;

    public function __construct(ETAService $etaService)
    {
        $this->etaService = $etaService;
    }

    /**
     * Get Transport Status for All Children Belonging to Authenticated Parent (Section 36).
     */
    public function childrenTransport(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $user = Auth::user();
        if (!$user) {
            return ResponseService::errorResponse("Unauthorized", null, 401);
        }

        try {
            // Find children linked to this parent/guardian
            $children = Students::where('guardian_id', $user->id)
            ->with(['user', 'class_section.class', 'class_section.section'])
            ->get();

            $transportOverview = [];

            foreach ($children as $child) {
                // Find active transport assignment
                $assignment = GpsStudentTransportAssignment::with(['route.routeVehicle.vehicle', 'pickupStop', 'dropStop'])
                    ->where('student_id', $child->id)
                    ->where('status', 1)
                    ->first();

                if (!$assignment) {
                    $transportOverview[] = [
                        'student_id'   => $child->id,
                        'name'         => trim(($child->user->first_name ?? '') . ' ' . ($child->user->last_name ?? '')),
                        'class'        => ($child->class_section?->class?->name ?? '') . ' ' . ($child->class_section?->section?->name ?? ''),
                        'has_transport'=> false,
                        'status'       => 'NOT_ASSIGNED',
                    ];
                    continue;
                }

                // Check today's active or recent trip
                $activeAttendance = GpsStudentTripAttendance::with(['trip.vehicle', 'trip.driver'])
                    ->where('student_id', $child->id)
                    ->whereHas('trip', function ($q) {
                        $q->whereIn('status', ['ACTIVE', 'SCHEDULED', 'COMPLETED'])
                          ->whereDate('created_at', Carbon::today());
                    })
                    ->latest()
                    ->first();

                $trip = $activeAttendance?->trip;
                $vehicle = $trip?->vehicle ?? $assignment->route?->routeVehicle?->first()?->vehicle;

                $currentState = $vehicle ? VehicleCurrentState::find($vehicle->id) : null;

                $transportOverview[] = [
                    'student_id'      => $child->id,
                    'name'            => trim(($child->user->first_name ?? '') . ' ' . ($child->user->last_name ?? '')),
                    'class'           => ($child->class_section?->class?->name ?? '') . ' ' . ($child->class_section?->section?->name ?? ''),
                    'has_transport'   => true,
                    'route_name'      => $assignment->route?->name ?? 'Assigned Route',
                    'pickup_stop'     => $assignment->pickupStop?->name ?? 'Pickup Point',
                    'drop_stop'       => $assignment->dropStop?->name ?? 'Drop Point',
                    'vehicle_number'  => $vehicle?->vehicle_number ?? 'Assigned Bus',
                    'vehicle_id'      => $vehicle?->id,
                    'trip_id'         => $trip?->id,
                    'trip_status'     => $trip?->status ?? 'NOT_STARTED',
                    'boarding_status' => $activeAttendance?->status ?? 'EXPECTED',
                    'driver_name'     => $trip?->driver?->full_name ?? 'Assigned Driver',
                    'eta'             => $currentState?->eta ?? 'N/A',
                ];
            }

            return response()->json([
                'error'    => false,
                'children' => $transportOverview,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parent Live Bus Tracking (Section 35 & 68).
     * Strictly verifies parent authorization to prevent unauthorized access.
     */
    public function trackBus(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        $user = Auth::user();

        // 1. Strict Authorization Verification: Verify child belongs to authenticated parent
        $child = Students::where('id', $request->student_id)
            ->where('guardian_id', $user->id)
            ->with(['user'])
            ->first();

        if (!$child) {
            return response()->json([
                'error'   => true,
                'message' => 'Unauthorized: This child is not associated with your parent account.',
            ], 403);
        }

        // 2. Fetch Student Transport Assignment
        $assignment = GpsStudentTransportAssignment::with(['route', 'pickupStop', 'dropStop'])
            ->where('student_id', $child->id)
            ->where('status', 1)
            ->first();

        if (!$assignment) {
            return response()->json([
                'error'   => true,
                'message' => 'No active transport assignment found for this child.',
            ], 404);
        }

        // 3. Find Active Trip
        $trip = GpsTrip::with(['vehicle', 'driver', 'tripStops.stop'])
            ->where('route_id', $assignment->route_id)
            ->whereIn('status', ['ACTIVE', 'SCHEDULED', 'PAUSED'])
            ->latest('actual_start_time')
            ->first();

        if (!$trip) {
            return response()->json([
                'error'        => false,
                'trip_active'  => false,
                'message'      => 'No active trip currently in progress for this route.',
                'child_name'   => trim(($child->user->first_name ?? '') . ' ' . ($child->user->last_name ?? '')),
                'route_name'   => $assignment->route?->name,
                'pickup_stop'  => $assignment->pickupStop?->name,
                'drop_stop'    => $assignment->dropStop?->name,
            ]);
        }

        // 4. Fetch Live Telemetry from VehicleCurrentState
        $currentState = VehicleCurrentState::find($trip->vehicle_id);

        // 5. Child's specific stop ETA
        $childStopId = ($trip->trip_type === 'EVENING') ? $assignment->drop_stop_id : $assignment->pickup_stop_id;
        $childStop = ($trip->trip_type === 'EVENING') ? $assignment->dropStop : $assignment->pickupStop;

        $attendance = GpsStudentTripAttendance::where('trip_id', $trip->id)
            ->where('student_id', $child->id)
            ->first();

        // Parents see the bus only while it is actually running a trip (data minimisation),
        // and are told when the last fix is too old to trust.
        $live = $trip->status === 'ACTIVE' && $currentState?->last_location_at;
        $lastAt = $currentState?->last_location_at;
        $stale = !$lastAt || $lastAt->diffInSeconds(Carbon::now()) > (int) config('gpstransport.defaults.stale_threshold_seconds', 300);

        return response()->json([
            'error'           => false,
            'trip_active'     => true,
            'trip_id'         => $trip->id,
            'trip_status'     => $trip->status,
            'child_name'      => trim(($child->user->first_name ?? '') . ' ' . ($child->user->last_name ?? '')),
            'boarding_status' => $attendance?->status ?? 'EXPECTED',
            'boarded_at'      => $attendance?->boarded_at?->format('h:i A'),
            'dropped_at'      => $attendance?->dropped_at?->format('h:i A'),
            'vehicle'         => [
                'vehicle_id'     => $trip->vehicle_id,
                'vehicle_number' => $trip->vehicle?->vehicle_number ?? 'School Bus',
                'latitude'       => $live ? (float) $currentState->latitude : null,
                'longitude'      => $live ? (float) $currentState->longitude : null,
                'speed'          => $live ? (float) ($currentState->speed ?? 0.0) : 0.0,
                'heading'        => $live ? (float) ($currentState->heading ?? 0.0) : 0.0,
                'status'         => $currentState?->status ?? 'UNKNOWN',
                'gps_status'     => $stale ? 'STALE' : ($currentState?->gps_status ?? 'CONNECTED'),
                'stale'          => $stale,
                'last_update'    => $lastAt ? $lastAt->diffForHumans() : 'No signal yet',
                'last_update_at' => $lastAt?->toIso8601String(),
            ],
            'driver'          => [
                'name'  => $trip->driver?->full_name ?? 'School Bus Driver',
                'phone' => $trip->driver?->mobile ?? '',
            ],
            'map'             => [
                'tile_url'    => config('gpstransport.map.tile_url'),
                'attribution' => config('gpstransport.map.attribution'),
            ],
            'child_stop'      => [
                'name'      => $childStop?->name ?? 'Assigned Stop',
                'latitude'  => (float) ($childStop?->latitude ?? 0.0),
                'longitude' => (float) ($childStop?->longitude ?? 0.0),
            ],
            'eta'             => $currentState?->eta ?? 'Calculating...',
            'next_stop'       => $currentState?->nextStop?->name ?? 'En Route',
        ]);
    }

    /**
     * Boarding History for the Parent's Child.
     */
    public function history(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        $user = Auth::user();

        // Verification
        $child = Students::where('id', $request->student_id)
            ->where('guardian_id', $user->id)
            ->first();

        if (!$child) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 403);
        }

        $events = GpsBoardingEvent::with(['vehicle', 'trip.route', 'stop'])
            ->where('student_id', $child->id)
            ->latest('timestamp')
            ->take(30)
            ->get()
            ->map(function ($ev) {
                return [
                    'event_type'     => $ev->event_type,
                    'stop_name'      => $ev->stop?->name ?? 'School',
                    'vehicle_number' => $ev->vehicle?->vehicle_number ?? 'Bus',
                    'route_name'     => $ev->trip?->route?->name ?? 'Route',
                    'timestamp'      => $ev->timestamp->format('d M Y, h:i A'),
                ];
            });

        return response()->json([
            'error'   => false,
            'history' => $events,
        ]);
    }
}
