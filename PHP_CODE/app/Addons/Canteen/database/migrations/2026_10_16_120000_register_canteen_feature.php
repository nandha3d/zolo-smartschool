<?php

use App\Addons\AddonRegistry;
use App\Addons\Canteen\CanteenServiceProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        AddonRegistry::registerFeature(CanteenServiceProvider::FEATURE);
    }

    public function down(): void
    {
        AddonRegistry::unregisterFeature(CanteenServiceProvider::FEATURE);
    }
};
