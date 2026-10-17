<?php

namespace App\Addons\Hostel;

use Illuminate\Support\ServiceProvider;

/**
 * Hostel Management: blocks, room types, rooms and beds; bed allocation and
 * transfers; morning / night roll call with guardian alerts; gate passes approved by
 * both guardian and warden and scanned at the gate; a visitor log; and hostel dues.
 *
 * Self-contained like the other addons. Outside this folder it only needs its
 * provider registered, a sidebar entry, its permissions in App\Addons\AddonRegistry,
 * and the tenant migration copied into database/migrations/schools.
 */
class HostelServiceProvider extends ServiceProvider
{
    public const FEATURE = 'Hostel Management';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'hostel');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');
    }
}
