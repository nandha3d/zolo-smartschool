<?php

namespace App\Addons\GpsTransport\Console\Commands;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsAlertRule;
use App\Addons\GpsTransport\Models\GpsSetting;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * GpsMigrateCommand
 *
 * Runs master feature registration and provisions all tenant school databases with GPS tables.
 */
class GpsMigrateCommand extends Command
{
    protected $signature = 'gps:migrate {--school= : Optional specific school ID to migrate}';
    protected $description = 'Provision master and school databases with GPS Transport Tracking tables and defaults';

    public function handle(): int
    {
        $this->info("🚀 Starting GPS Transport Tracking Module Migration...");

        // 1. Run Master Database Migrations
        $this->info("📦 Registering feature in master database...");
        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path'     => 'app/Addons/GpsTransport/database/migrations',
            '--force'    => true,
        ]);
        $this->line(Artisan::output());

        // 2. Run School Tenant Migrations
        $specificSchoolId = $this->option('school');
        $schoolsQuery = School::on('mysql')->withTrashed();
        if ($specificSchoolId) {
            $schoolsQuery->where('id', $specificSchoolId);
        }
        $schools = $schoolsQuery->get();

        $this->info("🏫 Migrating " . $schools->count() . " school tenant database(s)...");

        foreach ($schools as $school) {
            if (empty($school->database_name)) {
                continue;
            }

            $this->comment("👉 Migrating school: {$school->name} (DB: {$school->database_name})");

            try {
                Config::set('database.connections.school.database', $school->database_name);
                DB::purge('school');
                DB::connection('school')->reconnect();
                DB::setDefaultConnection('school');

                Artisan::call('migrate', [
                    '--database' => 'school',
                    '--path'     => 'database/migrations/schools',
                    '--force'    => true,
                ]);

                // Seed Default Settings & Alert Rules for this school
                $this->seedSchoolDefaults();

                $this->info("   ✅ Successfully migrated {$school->name}");
            } catch (\Throwable $e) {
                $this->error("   ❌ Failed on {$school->name}: " . $e->getMessage());
            }
        }

        $this->info("🎉 GPS Transport Tracking Module setup complete!");
        return self::SUCCESS;
    }

    private function seedSchoolDefaults(): void
    {
        // 1. Alert Rules
        $rules = [
            ['rule_type' => 'OVERSPEED', 'threshold_value' => 45.0, 'duration_seconds' => 20, 'notify_admin' => true, 'notify_parents' => false],
            ['rule_type' => 'ROUTE_DEVIATION', 'threshold_value' => 300.0, 'duration_seconds' => 60, 'notify_admin' => true, 'notify_parents' => true],
            ['rule_type' => 'LONG_STOP', 'threshold_value' => 0.0, 'duration_seconds' => 600, 'notify_admin' => true, 'notify_parents' => false],
            ['rule_type' => 'GPS_OFFLINE', 'threshold_value' => 300.0, 'duration_seconds' => 300, 'notify_admin' => true, 'notify_parents' => false],
            ['rule_type' => 'MOCK_LOCATION', 'threshold_value' => 1.0, 'duration_seconds' => 0, 'notify_admin' => true, 'notify_parents' => false],
        ];

        foreach ($rules as $r) {
            GpsAlertRule::firstOrCreate(['rule_type' => $r['rule_type']], $r);
        }

        // 2. Settings
        $settings = [
            'moving_interval_seconds'         => '10',
            'stopped_interval_seconds'        => '30',
            'default_speed_limit'             => '45',
            'default_stop_radius'             => '100',
            'approaching_radius'              => '500',
            'school_geofence_radius'          => '200',
            'route_deviation_distance_meters' => '300',
            'route_deviation_duration_seconds'=> '60',
            'stale_threshold_seconds'         => '300',
            'notify_parents_on_approaching'   => '1',
            'notify_parents_on_arrival'       => '1',
            'notify_parents_on_boarding'      => '1',
            'notify_parents_on_drop'          => '1',
            'notify_parents_on_delay'         => '1',
        ];

        foreach ($settings as $k => $v) {
            GpsSetting::firstOrCreate(['setting_key' => $k], ['setting_value' => $v]);
        }
    }
}
