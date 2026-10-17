<?php

use App\Addons\AddonRegistry;
use App\Addons\Library\LibraryServiceProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        AddonRegistry::registerFeature(LibraryServiceProvider::FEATURE);
    }

    public function down(): void
    {
        AddonRegistry::unregisterFeature(LibraryServiceProvider::FEATURE);
    }
};
