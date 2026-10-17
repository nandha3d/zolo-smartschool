<?php

use App\Addons\AddonRegistry;
use App\Addons\Hostel\HostelServiceProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        AddonRegistry::registerFeature(HostelServiceProvider::FEATURE);
    }

    public function down(): void
    {
        AddonRegistry::unregisterFeature(HostelServiceProvider::FEATURE);
    }
};
