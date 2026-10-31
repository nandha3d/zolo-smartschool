<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stop details for the route builder and separate morning (pickup) and afternoon (drop)
 * stop orders: the evening bus usually runs the morning route in reverse.
 * pickup_points.latitude / longitude / geofence_radius already come from the GPS addon.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pickup_points')) {
            Schema::table('pickup_points', function (Blueprint $table) {
                if (!Schema::hasColumn('pickup_points', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('name');
                }
                if (!Schema::hasColumn('pickup_points', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (!Schema::hasColumn('pickup_points', 'geofence_radius')) {
                    $table->decimal('geofence_radius', 8, 2)->default(100.00);
                }
                if (!Schema::hasColumn('pickup_points', 'halt_duration_minutes')) {
                    $table->unsignedSmallInteger('halt_duration_minutes')->default(2);
                }
                if (!Schema::hasColumn('pickup_points', 'landmark')) {
                    $table->string('landmark', 255)->nullable();
                }
                if (!Schema::hasColumn('pickup_points', 'contact_person')) {
                    $table->string('contact_person', 191)->nullable();
                }
                if (!Schema::hasColumn('pickup_points', 'contact_phone')) {
                    $table->string('contact_phone', 30)->nullable();
                }
            });
        }

        if (Schema::hasTable('route_pickup_points')) {
            Schema::table('route_pickup_points', function (Blueprint $table) {
                if (!Schema::hasColumn('route_pickup_points', 'morning_order')) {
                    $table->unsignedInteger('morning_order')->nullable()->after('order');
                }
                if (!Schema::hasColumn('route_pickup_points', 'afternoon_order')) {
                    $table->unsignedInteger('afternoon_order')->nullable()->after('morning_order');
                }
                if (!Schema::hasColumn('route_pickup_points', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('afternoon_order');
                }
            });

            // Existing routes: morning keeps the current order, afternoon runs it in reverse.
            DB::table('route_pickup_points')->whereNull('morning_order')->update(['morning_order' => DB::raw('`order`')]);
            $routes = DB::table('route_pickup_points')->whereNull('afternoon_order')->distinct()->pluck('route_id');
            foreach ($routes as $routeId) {
                $stops = DB::table('route_pickup_points')->where('route_id', $routeId)->orderByDesc('order')->pluck('id');
                foreach ($stops as $i => $id) {
                    DB::table('route_pickup_points')->where('id', $id)->update(['afternoon_order' => $i + 1]);
                }
            }
        }

        if (Schema::hasTable('routes')) {
            Schema::table('routes', function (Blueprint $table) {
                if (!Schema::hasColumn('routes', 'start_landmark')) {
                    $table->string('start_landmark', 255)->nullable();
                }
                if (!Schema::hasColumn('routes', 'end_landmark')) {
                    $table->string('end_landmark', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('routes')) {
            Schema::table('routes', function (Blueprint $table) {
                $table->dropColumn(['start_landmark', 'end_landmark']);
            });
        }
        if (Schema::hasTable('route_pickup_points')) {
            Schema::table('route_pickup_points', function (Blueprint $table) {
                $table->dropColumn(['morning_order', 'afternoon_order', 'is_active']);
            });
        }
        if (Schema::hasTable('pickup_points')) {
            Schema::table('pickup_points', function (Blueprint $table) {
                $table->dropColumn(['halt_duration_minutes', 'landmark', 'contact_person', 'contact_phone']);
            });
        }
    }
};
