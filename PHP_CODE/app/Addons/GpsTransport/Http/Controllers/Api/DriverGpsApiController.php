<?php

namespace App\Addons\GpsTransport\Http\Controllers\Api;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsBoardingEvent;
use App\Addons\GpsTransport\Models\GpsSession;
use App\Addons\GpsTransport\Models\GpsStudentTripAttendance;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\GpsTripStop;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Addons\GpsTransport\Services\EmergencyService;
use App\Addons\GpsTransport\Services\GpsNotificationService;
use App\Addons\GpsTransport\Services\LocationGateway;
use App\Addons\GpsTransport\Services\TrackingEngine;
use App\Http\Controllers\Controller;
use App\Models\RouteVehicle;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * DriverGpsApiController
 *
 * Implements Mobile GPS Driver App Endpoints (Sections 8, 10, 16, 17, 19, 31, 43, 46 & 48).
 */
class DriverGpsApiController extends Controller
{
    private LocationGateway $locationGateway;
    private TrackingEngine $trackingEngine;
    private EmergencyService $emergencyService;
    private GpsNotificationService $notificationService;

    public function __construct(
        LocationGateway $locationGateway,
        TrackingEngine $trackingEngine,
        EmergencyService $emergencyService,
        GpsNotificationService $notificationService
    ) {
        $this->locationGateway = $locationGateway;
        $this->trackingEngine = $trackingEngine;
        $this->emergencyService = $emergencyService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get Today's Assigned Trips for the Driver.
     */
    public function todayTrips(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $driver = Auth::user();
        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        try {
            $trips = GpsTrip::with([
                'vehicle',
                'route.routePickupPoints.pickupPoint',
                'tripStops.stop',
                'studentAttendances.student.user',
            ])
            ->whereIn('status', ['SCHEDULED', 'READY', 'ACTIVE', 'PAUSED'])
            ->get()
            ->filter(fn ($trip) => $trip->status === 'ACTIVE'
                ? (int) $trip->driver_id === (int) $driver->id
                : $this->mayDrive($trip, (int) $driver->id))
            ->values()
            ->map(function ($trip) {
                return [
                    'trip_id'          => $trip->id,
                    'trip_number'      => $trip->trip_number,
                    'trip_type'        => $trip->trip_type,
                    'status'           => $trip->status,
                    'vehicle_number'   => $trip->vehicle?->vehicle_number ?? 'N/A',
                    'vehicle_id'       => $trip->vehicle_id,
                    'route_id'         => $trip->route_id,
                    'route_name'       => $trip->route?->name ?? 'Unassigned',
                    'scheduled_time'   => $trip->scheduled_start_time ? $trip->scheduled_start_time->format('h:i A') : null,
                    'total_students'   => $trip->studentAttendances->count(),
                    'boarded_students' => $trip->studentAttendances->where('status', 'BOARDED')->count(),
                    'dropped_students' => $trip->studentAttendances->where('status', 'DROPPED')->count(),
                    'stops'            => $trip->tripStops->map(fn($ts) => [
                        'stop_id'         => $ts->stop_id,
                        'sequence'        => $ts->sequence,
                        'name'            => $ts->stop?->name ?? 'Stop',
                        'latitude'        => (float) ($ts->stop?->latitude ?? 0.0),
                        'longitude'       => (float) ($ts->stop?->longitude ?? 0.0),
                        'geofence_radius' => (float) ($ts->stop?->geofence_radius ?? 100.0),
                        'planned_time'    => $ts->planned_arrival_time,
                        'status'          => $ts->status,
                    ]),
                ];
            });

            return response()->json([
                'error'   => false,
                'data'    => $trips,
                'config'  => [
                    'moving_interval_seconds'  => (int) config('gpstransport.defaults.moving_interval_seconds', 10),
                    'stopped_interval_seconds' => (int) config('gpstransport.defaults.stopped_interval_seconds', 30),
                    'offline_sync_batch_size'  => (int) config('gpstransport.defaults.offline_sync_batch_size', 50),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Start a Trip (Driver initiates pre-flight checks and begins GPS session).
     */
    public function startTrip(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|integer|exists:gps_trips,id',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        try {
            $trip = GpsTrip::findOrFail($request->trip_id);
            $driver = Auth::user();

            // Only an unassigned trip, the driver's own, or one they are rostered to relieve;
            // and only one active trip per driver.
            if (!$this->mayDrive($trip, (int) $driver->id)) {
                return response()->json(['error' => true, 'message' => 'This trip is assigned to another driver.'], 403);
            }
            if (!in_array($trip->status, ['SCHEDULED', 'READY', 'PAUSED'], true)) {
                return response()->json(['error' => true, 'message' => "This trip is {$trip->status} and cannot be started."], 422);
            }
            $otherActive = GpsTrip::where('status', 'ACTIVE')->where('id', '!=', $trip->id)
                ->where(fn($q) => $q->where('driver_id', $driver->id)->orWhere('vehicle_id', $trip->vehicle_id))
                ->exists();
            if ($otherActive) {
                return response()->json(['error' => true, 'message' => 'You (or this vehicle) already have an active trip. End it first.'], 422);
            }

            $trip->status = 'ACTIVE';
            $trip->driver_id = $driver->id;
            $trip->actual_start_time = Carbon::now();
            $trip->save();

            // Create GPS session
            $session = GpsSession::create([
                'trip_id'    => $trip->id,
                'vehicle_id' => $trip->vehicle_id,
                'driver_id'  => $driver->id,
                'started_at' => Carbon::now(),
                'status'     => 'ACTIVE',
            ]);

            VehicleCurrentState::updateOrCreate(
                ['vehicle_id' => $trip->vehicle_id],
                [
                    'trip_id'    => $trip->id,
                    'driver_id'  => $driver->id,
                    'status'     => 'ON_TRIP',
                    'gps_status' => 'CONNECTED',
                ]
            );

            return response()->json([
                'error'        => false,
                'message'      => 'Trip started successfully. Mobile GPS tracking active.',
                'trip_id'      => $trip->id,
                'session_id'   => $session->id,
                'intervals'    => [
                    'moving_seconds'  => (int) config('gpstransport.defaults.moving_interval_seconds', 10),
                    'stopped_seconds' => (int) config('gpstransport.defaults.stopped_interval_seconds', 30),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ingest GPS Telemetry from Driver Mobile Phone.
     * Supports single location packets AND batch sync packets when network restores from offline mode (Section 19).
     */
    public function sendLocation(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $driver = Auth::user();
        $payloads = [];

        // Check if batch upload (offline sync queue) or single update
        if ($request->has('locations') && is_array($request->input('locations'))) {
            $payloads = $request->input('locations');
        } else {
            $payloads = [$request->all()];
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        $processedCount = 0;
        $rejectedCount = 0;
        $lastResult = null;
        $trips = [];

        foreach (array_slice($payloads, 0, 500) as $p) {
            if (!is_array($p)) {
                $rejectedCount++;
                continue;
            }
            // A point is accepted only for an ACTIVE trip this driver is running, and the
            // vehicle is taken from that trip, never from the client.
            $tripId = (int) ($p['trip_id'] ?? 0);
            $trips[$tripId] ??= GpsTrip::where('id', $tripId)->where('driver_id', $driver->id)->where('status', 'ACTIVE')->first();
            if (!$trips[$tripId]) {
                $rejectedCount++;
                continue;
            }
            $p['trip_id'] = $trips[$tripId]->id;
            $p['vehicle_id'] = $trips[$tripId]->vehicle_id;
            $p['driver_id'] = $driver->id;
            $p['source_type'] = 'MOBILE_APP';

            try {
                $processed = $this->locationGateway->process($p);
                $lastResult = $this->trackingEngine->ingest($processed['location'], $processed['flags']);
                $processedCount++;
            } catch (Throwable $e) {
                // Invalid point (validator, impossible jump …): skip it, keep the rest of the batch.
                $rejectedCount++;
                continue;
            }
        }

        return response()->json([
            'error'           => false,
            'processed_count' => $processedCount,
            'rejected_count'  => $rejectedCount,
            'current_state'   => $lastResult,
        ]);
    }

    /**
     * Record Student Attendance Event (Boarded / Dropped / Absent).
     */
    public function recordAttendance(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'trip_id'    => 'required|integer|exists:gps_trips,id',
            'student_id' => 'required|integer|exists:students,id',
            'event_type' => 'required|in:BOARDED,DROPPED,ABSENT,MANUAL_OVERRIDE',
            'stop_id'    => 'nullable|integer',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        try {
            $trip = GpsTrip::with('vehicle')->findOrFail($request->trip_id);
            $driver = Auth::user();
            $now = Carbon::now();

            if ((int) $trip->driver_id !== (int) $driver->id || $trip->status !== 'ACTIVE') {
                return response()->json(['error' => true, 'message' => 'Attendance can only be recorded on your active trip.'], 403);
            }
            if (!GpsStudentTripAttendance::where('trip_id', $trip->id)->where('student_id', $request->student_id)->exists()) {
                return response()->json(['error' => true, 'message' => 'This student is not on the trip roster.'], 422);
            }

            // 1. Record immutable boarding event
            GpsBoardingEvent::create([
                'trip_id'    => $trip->id,
                'vehicle_id' => $trip->vehicle_id,
                'driver_id'  => $driver?->id,
                'student_id' => $request->student_id,
                'stop_id'    => $request->stop_id,
                'event_type' => $request->event_type,
                'latitude'   => $request->latitude,
                'longitude'  => $request->longitude,
                'timestamp'  => $now,
                'notes'      => $request->notes,
            ]);

            // 2. Update roster record
            $attendance = GpsStudentTripAttendance::where('trip_id', $trip->id)
                ->where('student_id', $request->student_id)
                ->first();

            if ($attendance) {
                $attendance->status = $request->event_type;
                if ($request->event_type === 'BOARDED') {
                    $attendance->boarded_at = $now;
                } elseif ($request->event_type === 'DROPPED') {
                    $attendance->dropped_at = $now;
                }
                $attendance->save();
            }

            // 3. Update trip student count statistics
            $trip->boarded_students = GpsStudentTripAttendance::where('trip_id', $trip->id)->where('status', 'BOARDED')->count();
            $trip->dropped_students = GpsStudentTripAttendance::where('trip_id', $trip->id)->where('status', 'DROPPED')->count();
            $trip->save();

            // 4. Trigger Parent Notification
            $stopName = GpsTripStop::where('trip_id', $trip->id)->where('stop_id', $request->stop_id)->with('stop')->first()?->stop?->name ?? 'Bus Stop';
            if ($request->event_type === 'BOARDED') {
                $this->notificationService->sendStudentBoardedNotification($trip, $request->student_id, $stopName);
            } elseif ($request->event_type === 'DROPPED') {
                $this->notificationService->sendStudentDroppedNotification($trip, $request->student_id, $stopName);
            }

            return response()->json([
                'error'            => false,
                'message'          => 'Student attendance updated',
                'status'           => $request->event_type,
                'boarded_students' => $trip->boarded_students,
                'dropped_students' => $trip->dropped_students,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Driver SOS Trigger (Section 43).
     */
    public function triggerSos(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|integer',
            'latitude'   => 'required|numeric',
            'longitude'  => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        try {
            $driver = Auth::user();
            $tripId = $request->trip_id;
            $studentCount = (int) ($request->student_count ?? 0);
            if ($tripId && !$this->ownTrip((int) $tripId)) {
                return response()->json(['error' => true, 'message' => 'This trip is not yours.'], 403);
            }
            if ($tripId) {
                $request->merge(['vehicle_id' => $this->ownTrip((int) $tripId)->vehicle_id]);
            }

            if ($tripId && !$studentCount) {
                $studentCount = GpsStudentTripAttendance::where('trip_id', $tripId)->where('status', 'BOARDED')->count();
            }

            $incident = $this->emergencyService->triggerSos(
                (int) $request->vehicle_id,
                $driver?->id,
                $tripId ? (int) $tripId : null,
                (float) $request->latitude,
                (float) $request->longitude,
                $studentCount,
                $request->emergency_type ?? 'SOS',
                $request->description
            );

            return response()->json([
                'error'           => false,
                'message'         => 'Emergency SOS transmitted to School Control Center',
                'incident_number' => $incident->incident_number,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Driver Vehicle Breakdown Report (Section 46).
     */
    public function reportBreakdown(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'vehicle_id'       => 'required|integer',
            'problem_category' => 'required|string',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        try {
            $driver = Auth::user();
            if ($request->trip_id && !$this->ownTrip((int) $request->trip_id)) {
                return response()->json(['error' => true, 'message' => 'This trip is not yours.'], 403);
            }
            if ($request->trip_id) {
                $request->merge(['vehicle_id' => $this->ownTrip((int) $request->trip_id)->vehicle_id]);
            }
            $incident = $this->emergencyService->reportBreakdown(
                (int) $request->vehicle_id,
                $driver?->id,
                $request->trip_id ? (int) $request->trip_id : null,
                (float) $request->latitude,
                (float) $request->longitude,
                $request->problem_category,
                $request->description
            );

            return response()->json([
                'error'           => false,
                'message'         => 'Breakdown reported. Transport manager notified for replacement.',
                'incident_number' => $incident->incident_number,
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Complete Trip with Child Still Onboard Safety Check (Section 48).
     */
    public function endTrip(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|integer|exists:gps_trips,id',
        ]);

        if ($validator->fails()) {
            return ResponseService::validationError($validator->errors()->first());
        }

        if ($denied = $this->denyNonDriver()) {
            return $denied;
        }

        $trip = GpsTrip::with('studentAttendances.student.user')->findOrFail($request->trip_id);
        if ((int) $trip->driver_id !== (int) Auth::id() || $trip->status !== 'ACTIVE') {
            return response()->json(['error' => true, 'message' => 'Only your active trip can be ended.'], 403);
        }

        // Section 48: Child Still Onboard Safety Check
        $stillOnboard = $trip->studentAttendances->where('status', 'BOARDED');
        if ($stillOnboard->count() > 0 && !$request->has('override_reason')) {
            $names = $stillOnboard->map(fn($a) => $a->student?->user?->first_name ?? "Student #{$a->student_id}")->join(', ');
            return response()->json([
                'error'               => true,
                'still_onboard'       => true,
                'still_onboard_count' => $stillOnboard->count(),
                'students'            => $names,
                'message'             => "SAFETY WARNING: {$stillOnboard->count()} student(s) still registered onboard ({$names}). You must drop all students or provide an override reason to complete the trip.",
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
                $trip->notes = ($trip->notes ? $trip->notes . " | " : "") . "Override by driver: " . $request->override_reason;
            }

            $trip->save();

            // End active GPS session
            GpsSession::where('trip_id', $trip->id)
                ->where('status', 'ACTIVE')
                ->update(['ended_at' => $now, 'status' => 'ENDED']);

            VehicleCurrentState::where('vehicle_id', $trip->vehicle_id)->update([
                'trip_id' => null,
                'status'  => 'STOPPED',
            ]);

            return response()->json([
                'error'   => false,
                'message' => 'Trip successfully completed and GPS session closed',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * These endpoints move a school bus on every parent's map, so only Driver accounts
     * may call them (a student or parent token used to be enough).
     */
    private function denyNonDriver(): ?JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        if (!$user->hasRole('Driver')) {
            return response()->json(['error' => true, 'message' => 'Only drivers can use the trip endpoints.'], 403);
        }
        return null;
    }

    private function ownTrip(int $tripId): ?GpsTrip
    {
        return GpsTrip::where('id', $tripId)->where('driver_id', Auth::id())->first();
    }

    /**
     * May this driver run the trip today? A relief driver rostered on the trip's route
     * vehicle takes it over (and the primary driver is locked out); otherwise the trip's
     * own driver, or anyone for an unassigned trip.
     */
    private function mayDrive(GpsTrip $trip, int $userId): bool
    {
        $run = match ($trip->trip_type) {
            'MORNING' => 'pickup',
            'EVENING' => 'drop',
            default   => null,
        };
        $routeVehicle = RouteVehicle::where('route_id', $trip->route_id)->where('vehicle_id', $trip->vehicle_id)
            ->where('status', 1)->first();
        if ($routeVehicle) {
            $relief = $routeVehicle->alternateDrivers()->inForce(Carbon::today(), $run)->latest('id')->value('alternate_driver_id');
            if ($relief) {
                return (int) $relief === $userId;
            }
        }
        return !$trip->driver_id || (int) $trip->driver_id === $userId;
    }
}
