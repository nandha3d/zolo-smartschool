<?php

namespace App\Addons\GpsTransport\Console\Commands;

use App\Addons\GpsTransport\Services\TrackingEngine;
use App\Addons\GpsTransport\DTO\NormalizedLocation;
use App\Addons\GpsTransport\Models\GpsTrip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * GpsSimulateTelemetryCommand
 *
 * Simulates real-time telemetry streaming along a route for demonstrations and automated testing.
 */
class GpsSimulateTelemetryCommand extends Command
{
    protected $signature = 'gps:simulate {--vehicle= : Vehicle ID to simulate} {--trip= : Trip ID to simulate} {--steps=10 : Number of location steps} {--delay=2 : Delay in seconds between steps}';
    protected $description = 'Simulate real-time vehicle movement and telemetry ingestion';

    public function handle(TrackingEngine $trackingEngine): int
    {
        // Switch to school database if running in CLI
        $school = \App\Models\School::on('mysql')->first();
        if ($school && !empty($school->database_name)) {
            \Illuminate\Support\Facades\Config::set('database.connections.school.database', $school->database_name);
            \Illuminate\Support\Facades\DB::purge('school');
            \Illuminate\Support\Facades\DB::connection('school')->reconnect();
            \Illuminate\Support\Facades\DB::setDefaultConnection('school');
        }

        $vehicleId = (int) ($this->option('vehicle') ?: Vehicle::first()?->id);
        if (!$vehicleId) {
            $this->error("No vehicle available to simulate. Create a vehicle first.");
            return self::FAILURE;
        }

        $tripId = (int) ($this->option('trip') ?: GpsTrip::where('status', 'ACTIVE')->first()?->id);
        $steps = (int) $this->option('steps');
        $delay = (int) $this->option('delay');

        $this->info("🚌 Simulating vehicle #{$vehicleId}" . ($tripId ? " on trip #{$tripId}" : "") . " for {$steps} steps...");

        // Base coordinates (Coimbatore center or school coordinates)
        $startLat = 11.0168;
        $startLng = 76.9558;

        for ($i = 0; $i < $steps; $i++) {
            $currentLat = $startLat + ($i * 0.0015);
            $currentLng = $startLng + ($i * 0.0012);
            $speed = 32.0 + (rand(-5, 8)); // 27-40 km/h
            $heading = 45.0;

            $location = new NormalizedLocation([
                'vehicle_id'        => $vehicleId,
                'trip_id'           => $tripId ?: null,
                'latitude'          => $currentLat,
                'longitude'         => $currentLng,
                'accuracy'          => 8.0,
                'speed'             => $speed,
                'heading'           => $heading,
                'timestamp'         => Carbon::now(),
                'battery_level'     => max(20, 95 - $i),
                'is_mock_location'  => false,
                'source_type'       => 'MOBILE_APP',
                'source_identifier' => 'simulator',
                'network_type'      => '4G',
            ]);

            $result = $trackingEngine->ingest($location);

            $this->line(sprintf(
                "[%s] Step %d/%d -> Lat: %.5f, Lng: %.5f, Speed: %.1f km/h, Status: %s, Next Stop: %s, ETA: %s",
                Carbon::now()->format('H:i:s'),
                $i + 1,
                $steps,
                $currentLat,
                $currentLng,
                $speed,
                $result['status'],
                $result['next_stop'] ?? 'None',
                $result['eta'] ?? 'N/A'
            ));

            if ($i < $steps - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        $this->info("✅ Simulation complete!");
        return self::SUCCESS;
    }
}
