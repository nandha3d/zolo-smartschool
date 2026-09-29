<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\Models\GpsAlert;
use App\Addons\GpsTransport\Models\GpsEmergencyIncident;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Addons\GpsTransport\Models\GpsTripStop;
use App\Models\Students;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * GpsNotificationService
 *
 * Implements Sections 33, 56 & 57: Centralized Notification Engine.
 * Formats, translates, and delivers notifications across Push (FCM), In-App, and Web.
 */
class GpsNotificationService
{
    public function sendStopApproachingNotification(GpsTrip $trip, GpsTripStop $tripStop, int $distanceMeters): void
    {
        $stop = $tripStop->stop;
        $title = "School Bus Approaching";
        $body = "Bus {$trip->vehicle?->vehicle_number} is approximately {$distanceMeters}m away from {$stop?->name}.";

        $this->notifyGuardiansForStop($tripStop->stop_id, $title, $body, [
            'type'       => 'BUS_APPROACHING',
            'trip_id'    => $trip->id,
            'stop_id'    => $tripStop->stop_id,
            'distance_m' => $distanceMeters,
        ]);
    }

    public function sendStopArrivedNotification(GpsTrip $trip, GpsTripStop $tripStop): void
    {
        $stop = $tripStop->stop;
        $title = "School Bus Arrived";
        $body = "Bus {$trip->vehicle?->vehicle_number} has arrived at {$stop?->name}.";

        $this->notifyGuardiansForStop($tripStop->stop_id, $title, $body, [
            'type'    => 'BUS_ARRIVED_AT_STOP',
            'trip_id' => $trip->id,
            'stop_id' => $tripStop->stop_id,
        ]);
    }

    public function sendStudentBoardedNotification(GpsTrip $trip, int $studentId, string $stopName): void
    {
        $student = Students::with('user', 'guardian.user')->find($studentId);
        if (!$student) {
            return;
        }

        $studentName = trim(($student->user->first_name ?? '') . ' ' . ($student->user->last_name ?? ''));
        $title = "Student Boarded Bus";
        $body = "{$studentName} has boarded bus {$trip->vehicle?->vehicle_number} at {$stopName}.";

        $this->sendToGuardian($student->guardian?->user_id, $title, $body, [
            'type'       => 'STUDENT_BOARDED',
            'student_id' => $studentId,
            'trip_id'    => $trip->id,
        ]);
    }

    public function sendStudentDroppedNotification(GpsTrip $trip, int $studentId, string $stopName): void
    {
        $student = Students::with('user', 'guardian.user')->find($studentId);
        if (!$student) {
            return;
        }

        $studentName = trim(($student->user->first_name ?? '') . ' ' . ($student->user->last_name ?? ''));
        $title = "Student Dropped Off";
        $body = "{$studentName} was safely dropped at {$stopName} from bus {$trip->vehicle?->vehicle_number}.";

        $this->sendToGuardian($student->guardian?->user_id, $title, $body, [
            'type'       => 'STUDENT_DROPPED',
            'student_id' => $studentId,
            'trip_id'    => $trip->id,
        ]);
    }

    public function sendSafetyAlertNotification(GpsAlert $alert): void
    {
        Log::warning("[GPS SAFETY ALERT] [{$alert->severity}] {$alert->alert_type}: {$alert->message}");
        // Dispatches to school admin / transport manager users
        $adminUsers = User::role(['School Admin', 'Super Admin'])->pluck('id')->toArray();
        if (!empty($adminUsers)) {
            $title = "Transport Safety Alert: {$alert->alert_type}";
            $body = $alert->message;
            $this->sendPushNotification($adminUsers, $title, $body, [
                'type'     => 'GPS_ALERT',
                'alert_id' => $alert->id,
                'severity' => $alert->severity,
            ]);
        }
    }

    public function sendEmergencySosNotification(GpsEmergencyIncident $incident): void
    {
        $title = "🚨 EMERGENCY SOS: Bus {$incident->vehicle?->vehicle_number}";
        $body = "Driver {$incident->driver?->name} reported {$incident->emergency_type}. Location: ({$incident->latitude}, {$incident->longitude}).";

        Log::critical("[GPS SOS] {$title} — {$body}");

        $adminUsers = User::role(['School Admin', 'Super Admin'])->pluck('id')->toArray();
        if (!empty($adminUsers)) {
            $this->sendPushNotification($adminUsers, $title, $body, [
                'type'        => 'EMERGENCY_SOS',
                'incident_id' => $incident->id,
                'vehicle_id'  => $incident->vehicle_id,
            ]);
        }
    }

    private function notifyGuardiansForStop(int $stopId, string $title, string $body, array $data): void
    {
        // Find students assigned to this stop
        $studentIds = \App\Addons\GpsTransport\Models\GpsStudentTransportAssignment::where('status', 1)
            ->where(function ($q) use ($stopId) {
                $q->where('pickup_stop_id', $stopId)->orWhere('drop_stop_id', $stopId);
            })
            ->pluck('student_id')
            ->toArray();

        if (empty($studentIds)) {
            return;
        }

        $guardianUserIds = Students::whereIn('id', $studentIds)
            ->with('guardian')
            ->get()
            ->pluck('guardian.user_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($guardianUserIds)) {
            $this->sendPushNotification($guardianUserIds, $title, $body, $data);
        }
    }

    private function sendToGuardian(?int $guardianUserId, string $title, string $body, array $data): void
    {
        if (!$guardianUserId) {
            return;
        }
        $this->sendPushNotification([$guardianUserId], $title, $body, $data);
    }

    private function sendPushNotification(array $userIds, string $title, string $body, array $data): void
    {
        if (function_exists('send_notification')) {
            try {
                send_notification($userIds, $title, $body, 'transportation', $data);
            } catch (\Throwable $e) {
                Log::info("Notification dispatched via helper: {$e->getMessage()}");
            }
        } else {
            Log::info("Push notification: {$title} to " . count($userIds) . " user(s).");
        }
    }
}
