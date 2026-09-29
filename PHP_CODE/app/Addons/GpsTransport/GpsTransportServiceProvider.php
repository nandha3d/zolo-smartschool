<?php

namespace App\Addons\GpsTransport;

use App\Addons\GpsTransport\Console\Commands\GpsMigrateCommand;
use App\Addons\GpsTransport\Console\Commands\GpsSimulateTelemetryCommand;
use App\Addons\GpsTransport\Contracts\LocationProviderInterface;
use App\Addons\GpsTransport\Providers\MobileLocationProvider;
use Illuminate\Support\ServiceProvider;

/**
 * GPS Transport Tracking Addon Service Provider
 *
 * Self-contained addon module providing mobile-first GPS vehicle tracking,
 * route geofencing, student attendance, driver SOS, breakdown workflow, and
 * live parent tracking portal.
 *
 * Architecture boundary:
 * Downstream services depend on LocationProviderInterface, allowing seamless
 * transition or coexistence of Mobile App GPS and Dedicated Hardware GPS devices.
 */
class GpsTransportServiceProvider extends ServiceProvider
{
    public const FEATURE = 'GPS Transport Tracking';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/gpstransport.php', 'gpstransport');

        // Bind default LocationProviderInterface to MobileLocationProvider (Phase 1)
        $this->app->singleton(LocationProviderInterface::class, MobileLocationProvider::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GpsMigrateCommand::class,
                GpsSimulateTelemetryCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Load web and api routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Load Views under 'gpstransport' namespace
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'gpstransport');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Load JSON translations
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');

        // Publish config
        $this->publishes([
            __DIR__ . '/config/gpstransport.php' => config_path('gpstransport.php'),
        ], 'gps-transport-config');
    }
}
