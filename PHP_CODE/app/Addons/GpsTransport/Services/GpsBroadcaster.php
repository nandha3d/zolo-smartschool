<?php

namespace App\Addons\GpsTransport\Services;

use App\Addons\GpsTransport\DTO\NormalizedLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GpsBroadcaster
 *
 * Implements Section 22: WebSocket / Real-Time Updates.
 * Transmits vehicle location updates, ETA, and event broadcasts to connected web and mobile clients.
 */
class GpsBroadcaster
{
    /**
     * Broadcast live vehicle location update.
     */
    public function broadcastLocation(NormalizedLocation $location, array $etaData = [], string $status = 'MOVING'): void
    {
        $payload = [
            'event'       => 'vehicle.location.updated',
            'channel'     => "gps.fleet",
            'vehicle_id'  => $location->vehicleId,
            'driver_id'   => $location->driverId,
            'trip_id'     => $location->tripId,
            'latitude'    => $location->latitude,
            'longitude'   => $location->longitude,
            'speed'       => $location->speed,
            'heading'     => $location->heading,
            'accuracy'    => $location->accuracy,
            'status'      => $status,
            'eta'         => $etaData['destination_eta'] ?? null,
            'next_stop'   => $etaData['next_stop']['stop_name'] ?? null,
            'next_stop_eta' => $etaData['next_stop']['eta_time'] ?? null,
            'battery_level' => $location->batteryLevel,
            'timestamp'   => $location->timestamp->toIso8601String(),
        ];

        // 1. Update rolling broadcast buffer in Cache for polling / initial map sync
        Cache::put("gps_fleet_latest_{$location->vehicleId}", $payload, 300);

        // 2. Dispatch to local Ratchet WebSocket server if enabled
        if (config('gpstransport.websocket.enabled', true)) {
            $this->sendToRatchetWebSocket($payload);
        }
    }

    /**
     * Broadcast an emergency SOS event.
     */
    public function broadcastEmergency(int $vehicleId, string $type, string $message, float $lat, float $lng): void
    {
        $payload = [
            'event'      => 'vehicle.emergency',
            'channel'    => "gps.fleet",
            'vehicle_id' => $vehicleId,
            'type'       => $type,
            'message'    => $message,
            'latitude'   => $lat,
            'longitude'  => $lng,
            'timestamp'  => Carbon::now()->toIso8601String(),
        ];

        Cache::put("gps_emergency_latest", $payload, 600);

        if (config('gpstransport.websocket.enabled', true)) {
            $this->sendToRatchetWebSocket($payload);
        }
    }

    /**
     * Send JSON frame over TCP to local Ratchet WebSocket server.
     */
    private function sendToRatchetWebSocket(array $payload): void
    {
        try {
            $host = config('gpstransport.websocket.host', '127.0.0.1');
            $port = config('gpstransport.websocket.port', 8090);

            $socket = @fsockopen($host, $port, $errno, $errstr, 0.2);
            if ($socket) {
                $message = json_encode([
                    'command' => 'groupchat',
                    'channel' => 'gps.fleet',
                    'message' => json_encode($payload),
                ]);
                fwrite($socket, $message . "\n");
                fclose($socket);
            }
        } catch (\Throwable $e) {
            // Non-blocking fallback: client can poll /api/school/gps/fleet-live
        }
    }
}
