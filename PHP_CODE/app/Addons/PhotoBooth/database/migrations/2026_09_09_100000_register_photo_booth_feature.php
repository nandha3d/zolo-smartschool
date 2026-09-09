<?php

use App\Addons\PhotoBooth\PhotoBoothServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the Photo Booth as a sellable feature on the master database.
 *
 * It is created switched off (is_default = 0), so no school gets it until a Super
 * Admin attaches it to a package or sells it as an addon — the same way every other
 * feature on the platform is distributed.
 */
return new class extends Migration {
    private const NAME = PhotoBoothServiceProvider::FEATURE;

    public function up(): void
    {
        $exists = DB::connection('mysql')->table('features')->where('name', self::NAME)->exists();
        if ($exists) {
            return;
        }

        $row = ['name' => self::NAME, 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()];

        // status and required_vps arrived in later versions of the table; only send
        // them when they are actually there, so this runs on either schema.
        foreach (['status' => 1, 'required_vps' => 0] as $column => $value) {
            if (DB::connection('mysql')->getSchemaBuilder()->hasColumn('features', $column)) {
                $row[$column] = $value;
            }
        }

        DB::connection('mysql')->table('features')->insert($row);
    }

    public function down(): void
    {
        // Only the feature row is removed. Any package_features / subscription rows
        // pointing at it are cleared by their own cascade.
        DB::connection('mysql')->table('features')->where('name', self::NAME)->delete();
    }
};
