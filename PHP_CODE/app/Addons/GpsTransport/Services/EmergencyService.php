<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsEmergencyAction;
use App\Addons\GpsTransport\Models\GpsEmergencyIncident;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\VehicleCurrentState;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EmergencyService
 *
 * Implements Sections 43-47: SOS, Emergency Center, and Breakdown Workflows.
 */
class EmergencyService
{
    private GpsNotificationService $notificationService;

    public function __construct(GpsNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Trigger Emergency SOS from Driver App.
     */
    public function triggerSos(
        int $vehicleId,
        ?int $driverId,
        ?int $tripId,
        float $latitude,
        float $longitude,
        int $studentCount,
        string $emergencyType = 'SOS',
        ?string $description = null
    ): GpsEmergencyIncident {
        $incidentNumber = 'SOS-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $incident = GpsEmergencyIncident::create([
            'incident_number' => $incidentNumber,
            'trip_id'         => $tripId,
            'vehicle_id'      => $vehicleId,
            'driver_id'       => $driverId,
            'emergency_type'  => $emergencyType,
            'status'          => 'OPEN',
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'student_count'   => $studentCount,
            'description'     => $description ?: "Driver initiated emergency SOS trigger",
            'reported_at'     => Carbon::now(),
        ]);

        // Update vehicle state to EMERGENCY
        VehicleCurrentState::where('vehicle_id', $vehicleId)->update([
            'status' => 'EMERGENCY',
            'updated_at' => Carbon::now(),
        ]);

        if ($tripId) {
            GpsTrip::where('id', $tripId)->update(['status' => 'EMERGENCY']);
        }

        // Notify school administration
        $this->notificationService->sendEmergencySosNotification($incident);

        return $incident;
    }

    /**
     * Report Vehicle Breakdown.
     */
    public function reportBreakdown(
        int $vehicleId,
        ?int $driverId,
        ?int $tripId,
        float $latitude,
        float $longitude,
        string $problemCategory,
        ?string $description = null
    ): GpsEmergencyIncident {
        $incidentNumber = 'BD-' . Carbon::now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $incident = GpsEmergencyIncident::create([
            'incident_number' => $incidentNumber,
            'trip_id'         => $tripId,
            'vehicle_id'      => $vehicleId,
            'driver_id'       => $driverId,
            'emergency_type'  => 'VEHICLE_BREAKDOWN',
            'status'          => 'OPEN',
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'student_count'   => 0,
            'description'     => "[{$problemCategory}] " . ($description ?: "Vehicle breakdown reported"),
            'reported_at'     => Carbon::now(),
        ]);

        VehicleCurrentState::where('vehicle_id', $vehicleId)->update([
            'status' => 'BREAKDOWN',
            'updated_at' => Carbon::now(),
        ]);

        if ($tripId) {
            GpsTrip::where('id', $tripId)->update(['status' => 'PAUSED']);
        }

        return $incident;
    }

    /**
     * Assign replacement vehicle and transfer active trip operations.
     */
    public function assignReplacementVehicle(
        int $incidentId,
        int $replacementVehicleId,
        ?int $replacementDriverId,
        int $actionByUserId,
        ?string $actionNotes = null
    ): bool {
        return DB::transaction(function () use (
            $incidentId,
            $replacementVehicleId,
            $replacementDriverId,
            $actionByUserId,
            $actionNotes
        ) {
            $incident = GpsEmergencyIncident::findOrFail($incidentId);

            // Record action
            GpsEmergencyAction::create([
                'incident_id'            => $incident->id,
                'action_type'            => 'ASSIGN_REPLACEMENT_BUS',
                'description'            => $actionNotes ?: "Assigned replacement vehicle #{$replacementVehicleId}",
                'performed_by'           => $actionByUserId,
                'replacement_vehicle_id' => $replacementVehicleId,
                'replacement_driver_id'  => $replacementDriverId,
            ]);

            // Update incident status
            $incident->status = 'RESOLVED';
            $incident->resolved_at = Carbon::now();
            $incident->resolved_by = $actionByUserId;
            $incident->save();

            // Transfer trip to new vehicle if active
            if ($incident->trip_id) {
                $trip = GpsTrip::find($incident->trip_id);
                if ($trip) {
                    $trip->vehicle_id = $replacementVehicleId;
                    if ($replacementDriverId) {
                        $trip->driver_id = $replacementDriverId;
                    }
                    $trip->status = 'ACTIVE';
                    $trip->notes = ($trip->notes ? $trip->notes . " | " : "") . "Transferred from breakdown vehicle #{$incident->vehicle_id}";
                    $trip->save();
                }
            }

            return true;
        });
    }
}
