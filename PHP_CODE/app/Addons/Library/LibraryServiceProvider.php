<?php

namespace App\Addons\Library;

use App\Addons\Library\Console\LibraryRefreshCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Library Management: catalogue with physical copies and barcodes, a scan-driven
 * issue / return desk, renewals, a reservation queue, overdue fines, an e-book shelf,
 * and mobile endpoints for students, staff and parents.
 *
 * Self-contained like the other addons: routes, views, models and migrations live
 * here. Outside this folder it only needs its provider registered, a sidebar entry,
 * its permissions listed in App\Addons\AddonRegistry, and the tenant migration copied
 * into database/migrations/schools.
 */
class LibraryServiceProvider extends ServiceProvider
{
    public const FEATURE = 'Library Management';

    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([LibraryRefreshCommand::class]);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'library');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule) {
            $schedule->command('library:refresh')->dailyAt('00:30');
        });
    }
}
