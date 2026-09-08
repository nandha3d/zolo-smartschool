<?php

namespace App\Console\Commands;

use App\Jobs\SetupSchoolDatabase;
use App\Models\Feature;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\School;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a complete, working demo: one school with its own tenant database, an active
 * subscription covering every feature, and a signed-in-ready account for each role.
 *
 * This runs the same provisioning path production uses (SetupSchoolDatabase), rather
 * than writing tenant rows into the central database the way the old DummyDataSeeder
 * did - that seeder predates the multi-tenancy refactor and would produce a school
 * with no database_name and academic data in the wrong schema.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:setup {--fresh : Drop and recreate the demo school}';

    protected $description = 'Create a fully working demo school with data and one login per role';

    /** Demo passwords are intentionally fixed and printed. This is a demo. */
    private const DEMO_PASSWORD = 'Demo@12345';

    private const SCHOOL_NAME = 'Zolo Demo School';
    private const SCHOOL_CODE = 'DEMO001';

    public function handle(): int
    {
        DB::setDefaultConnection('mysql');

        $this->components->info('Preparing demo environment');

        $this->relaxSetupGates();
        $package = $this->ensurePackage();
        $school = $this->ensureSchool();

        if (!$school) {
            return self::FAILURE;
        }

        $this->components->task('Provisioning tenant database (this runs the full migration set)', function () use ($school, $package) {
            (new SetupSchoolDatabase($school->id, $package->id, 'DEMO'))
                ->handle(
                    app(\App\Services\SchoolDataService::class),
                    app(\App\Services\SubscriptionService::class),
                    app(\App\Services\CachingService::class),
                    app(\App\Repositories\SystemSetting\SystemSettingInterface::class)
                );
            return true;
        });

        DB::setDefaultConnection('mysql');
        $school->refresh();

        $this->newLine();
        $this->components->info('Demo school ready');
        $this->table(['', 'Value'], [
            ['School', $school->name],
            ['School code', $school->code],
            ['Tenant database', $school->database_name],
            ['Installed', $school->installed ? 'yes' : 'no'],
        ]);

        $this->newLine();
        $this->line('  Now run <options=bold>php artisan demo:content</> to add classes, staff and students.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * The super admin is otherwise held in the setup wizard, and school creation
     * refuses to run until email verification has been switched on.
     */
    private function relaxSetupGates(): void
    {
        $this->components->task('Clearing setup wizard and email-verification gates', function () {
            SystemSetting::where('name', 'like', '%wizard_checkMark')->update(['data' => 1]);
            SystemSetting::updateOrCreate(['name' => 'email_verified'], ['data' => 1, 'type' => 'boolean']);
            app(\App\Services\CachingService::class)->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
            return true;
        });
    }

    private function ensurePackage(): Package
    {
        $package = Package::where('name', 'Demo Plan')->first();

        if (!$package) {
            $package = new Package();
            $package->name = 'Demo Plan';
            $package->description = 'Everything enabled, for demonstration.';
            $package->tagline = 'All features';
            $package->student_charge = 0;
            $package->staff_charge = 0;
            $package->charges = 0;
            $package->no_of_students = 5000;
            $package->no_of_staffs = 500;
            $package->days = 3650;
            $package->type = 1;      // prepaid
            $package->is_trial = 0;
            $package->highlight = 1;
            $package->rank = 1;
            $package->status = 1;
            $package->save();
        }

        // Attach every feature, so nothing in the UI is gated off during the demo.
        $featureIds = Feature::pluck('id');
        foreach ($featureIds as $featureId) {
            PackageFeature::firstOrCreate([
                'package_id' => $package->id,
                'feature_id' => $featureId,
            ]);
        }

        $this->components->twoColumnDetail('Package "Demo Plan"', $featureIds->count() . ' features attached');

        return $package;
    }

    private function ensureSchool(): ?School
    {
        $existing = School::where('code', self::SCHOOL_CODE)->first();

        if ($existing && !$this->option('fresh')) {
            $this->components->warn('Demo school already exists. Re-run with --fresh to rebuild it.');
            return null;
        }

        if ($existing) {
            $this->components->task('Dropping the previous demo school', function () use ($existing) {
                if ($existing->database_name) {
                    DB::statement('DROP DATABASE IF EXISTS `' . $existing->database_name . '`');
                }
                User::where('school_id', $existing->id)->forceDelete();
                $existing->forceDelete();
                return true;
            });
        }

        $school = null;

        $this->components->task('Creating the school and its administrator', function () use (&$school) {
            $admin = User::create([
                'first_name' => 'Demo',
                'last_name'  => 'Principal',
                'email'      => 'admin@demo.test',
                'mobile'     => '9000000001',
                'gender'     => 'male',
                'password'   => Hash::make(self::DEMO_PASSWORD),
                // A demo should sign straight in rather than land on the change
                // password screen, so the hold is released deliberately here.
                'must_change_password' => 0,
                'status'     => 1,
                'email_verified_at' => now(),
                'two_factor_enabled' => 0,
                'image'      => 'logo.svg',
            ]);

            $school = School::create([
                'name'          => self::SCHOOL_NAME,
                'address'       => '1 Demo Street',
                'support_phone' => '9000000000',
                'support_email' => 'support@demo.test',
                'tagline'       => 'A working demonstration',
                'logo'          => 'logo.svg',
                'domain'        => 'demo',
                'code'          => self::SCHOOL_CODE,
                'type'          => 'custom',
                'domain_type'   => 'default',
                'installed'     => 0,
                'status'        => 1,
                'admin_id'      => $admin->id,
            ]);

            $school->database_name = 'zolo_schools_' . $school->id . '_demo';
            $school->save();

            $admin->school_id = $school->id;
            $admin->save();
            // The School Admin role is not assigned here: it lives in the tenant
            // database, and SchoolDataService::createPreSetupRole() assigns it there
            // once the tenant has been provisioned. Only Super Admin exists centrally.

            return true;
        });

        return $school;
    }
}
