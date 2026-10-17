<?php

namespace App\Addons\Canteen;

use Illuminate\Support\ServiceProvider;

/**
 * Canteen & cashless wallet: menu with dietary and allergen tags, a touch POS that
 * identifies students by RFID card, wallet top-ups with a full ledger, parental daily
 * limits / blocked categories / allergy interlock, a kitchen order board, pre-orders
 * from the app, raw-material inventory and meal plans.
 *
 * Self-contained like the other addons. Outside this folder it only needs its
 * provider registered, a sidebar entry, its permissions in App\Addons\AddonRegistry,
 * and the tenant migration copied into database/migrations/schools.
 */
class CanteenServiceProvider extends ServiceProvider
{
    public const FEATURE = 'Canteen Management';

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'canteen');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__ . '/lang');
    }
}
