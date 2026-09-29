<?php

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers 'GPS Transport Tracking' as a sellable feature on the master database.
 * Created with is_default = 0, so no school receives it until enabled by Super Admin
 * or included in an enterprise subscription.
 */
return new class extends Migration {
    private const NAME = GpsTransportServiceProvider::FEATURE;

    public function up(): void
    {
        $exists = DB::connection('mysql')->table('features')->where('name', self::NAME)->exists();
        if ($exists) {
            return;
        }

        $row = [
            'name'       => self::NAME,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach (['status' => 1, 'required_vps' => 0] as $column => $value) {
            if (DB::connection('mysql')->getSchemaBuilder()->hasColumn('features', $column)) {
                $row[$column] = $value;
            }
        }

        DB::connection('mysql')->table('features')->insert($row);
    }

    public function down(): void
    {
        DB::connection('mysql')->table('features')->where('name', self::NAME)->delete();
    }
};
