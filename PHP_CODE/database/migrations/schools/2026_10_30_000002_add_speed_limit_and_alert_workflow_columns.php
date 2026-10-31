<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-vehicle speed limits (a van and a 50-seat bus need different limits) and the
 * columns an admin needs to acknowledge an overspeed alert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'speed_limit_kmh')) {
                    $table->decimal('speed_limit_kmh', 5, 2)->nullable()->after('capacity');
                }
                if (!Schema::hasColumn('vehicles', 'speed_alert_cooldown_minutes')) {
                    $table->unsignedSmallInteger('speed_alert_cooldown_minutes')->default(3)->after('speed_limit_kmh');
                }
            });
        }

        if (Schema::hasTable('gps_alerts')) {
            Schema::table('gps_alerts', function (Blueprint $table) {
                if (!Schema::hasColumn('gps_alerts', 'driver_id')) {
                    $table->unsignedBigInteger('driver_id')->nullable()->after('vehicle_id');
                }
                if (!Schema::hasColumn('gps_alerts', 'route_id')) {
                    $table->unsignedBigInteger('route_id')->nullable()->after('driver_id');
                }
                if (!Schema::hasColumn('gps_alerts', 'speed_limit')) {
                    $table->decimal('speed_limit', 5, 2)->nullable()->after('speed');
                }
                if (!Schema::hasColumn('gps_alerts', 'peak_speed')) {
                    $table->decimal('peak_speed', 6, 2)->nullable()->after('speed_limit');
                }
                if (!Schema::hasColumn('gps_alerts', 'duration_seconds')) {
                    $table->unsignedInteger('duration_seconds')->default(0)->after('peak_speed');
                }
                if (!Schema::hasColumn('gps_alerts', 'acknowledged_by')) {
                    $table->unsignedBigInteger('acknowledged_by')->nullable()->after('resolved_by');
                }
                if (!Schema::hasColumn('gps_alerts', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
                }
                if (!Schema::hasColumn('gps_alerts', 'admin_remarks')) {
                    $table->string('admin_remarks', 500)->nullable()->after('acknowledged_at');
                }
            });

            Schema::table('gps_alerts', function (Blueprint $table) {
                $table->index(['alert_type', 'acknowledged_at'], 'gps_alerts_type_ack_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gps_alerts')) {
            Schema::table('gps_alerts', function (Blueprint $table) {
                $table->dropIndex('gps_alerts_type_ack_idx');
                $table->dropColumn([
                    'driver_id', 'route_id', 'speed_limit', 'peak_speed', 'duration_seconds',
                    'acknowledged_by', 'acknowledged_at', 'admin_remarks',
                ]);
            });
        }
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn(['speed_limit_kmh', 'speed_alert_cooldown_minutes']);
            });
        }
    }
};
