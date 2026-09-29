<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. location_sources
        if (!Schema::hasTable('location_sources')) {
            Schema::create('location_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->string('type', 32)->default('MOBILE_APP'); // 'MOBILE_APP' | 'GPS_DEVICE'
                $table->string('source_identifier')->nullable()->index();
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamp('last_seen_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 2. gps_sessions
        if (!Schema::hasTable('gps_sessions')) {
            Schema::create('gps_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->unsignedBigInteger('location_source_id')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_location_at')->nullable();
                $table->string('status', 32)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // 3. vehicle_locations (Historical Telemetry Breadcrumbs)
        if (!Schema::hasTable('vehicle_locations')) {
            Schema::create('vehicle_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->string('source_type', 32)->default('MOBILE_APP');
                $table->string('source_id')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->decimal('altitude', 8, 2)->nullable();
                $table->decimal('speed', 6, 2)->default(0.00); // km/h
                $table->decimal('heading', 6, 2)->default(0.00); // degrees
                $table->timestamp('timestamp')->index();
                $table->timestamp('received_at')->useCurrent();
                $table->smallInteger('battery_level')->nullable();
                $table->boolean('is_mock_location')->default(false);
                $table->string('network_type', 32)->default('4G');
                $table->text('flags')->nullable();
                $table->timestamps();

                $table->index(['vehicle_id', 'timestamp']);
                $table->index(['trip_id', 'timestamp']);
            });
        }

        // 4. vehicle_current_states (Fast Single-Row Telemetry Cache)
        if (!Schema::hasTable('vehicle_current_states')) {
            Schema::create('vehicle_current_states', function (Blueprint $table) {
                $table->unsignedBigInteger('vehicle_id')->primary();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed', 6, 2)->default(0.00);
                $table->decimal('heading', 6, 2)->default(0.00);
                $table->string('status', 32)->default('STOPPED'); // 'MOVING', 'STOPPED', 'OFFLINE', 'AT_SCHOOL', 'EMERGENCY', 'BREAKDOWN'
                $table->unsignedBigInteger('next_stop_id')->nullable();
                $table->string('eta', 32)->nullable();
                $table->string('gps_status', 32)->default('OFFLINE'); // 'CONNECTED', 'STALE', 'OFFLINE'
                $table->smallInteger('battery_level')->nullable();
                $table->boolean('is_mock_location')->default(false);
                $table->string('network_type', 32)->default('4G');
                $table->timestamp('last_location_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. gps_trips
        if (!Schema::hasTable('gps_trips')) {
            Schema::create('gps_trips', function (Blueprint $table) {
                $table->id();
                $table->string('trip_number', 64)->unique();
                $table->string('trip_type', 32)->default('MORNING'); // 'MORNING', 'EVENING', 'SPECIAL'
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->unsignedBigInteger('route_id')->nullable()->index();
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->string('status', 32)->default('SCHEDULED'); // 'SCHEDULED', 'READY', 'ACTIVE', 'PAUSED', 'COMPLETED', 'CANCELLED', 'EMERGENCY'
                $table->timestamp('scheduled_start_time')->nullable();
                $table->timestamp('actual_start_time')->nullable();
                $table->timestamp('actual_end_time')->nullable();
                $table->decimal('distance_km', 8, 3)->default(0.000);
                $table->integer('duration_minutes')->default(0);
                $table->integer('total_students')->default(0);
                $table->integer('boarded_students')->default(0);
                $table->integer('dropped_students')->default(0);
                $table->text('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 6. gps_trip_stops
        if (!Schema::hasTable('gps_trip_stops')) {
            Schema::create('gps_trip_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->index();
                $table->unsignedBigInteger('stop_id')->index();
                $table->integer('sequence')->default(1);
                $table->time('planned_arrival_time')->nullable();
                $table->time('planned_departure_time')->nullable();
                $table->time('actual_arrival_time')->nullable();
                $table->time('actual_departure_time')->nullable();
                $table->string('status', 32)->default('UPCOMING'); // 'UPCOMING', 'APPROACHING', 'ARRIVED', 'WAITING', 'DEPARTED', 'SKIPPED'
                $table->integer('expected_board')->default(0);
                $table->integer('actual_board')->default(0);
                $table->integer('expected_drop')->default(0);
                $table->integer('actual_drop')->default(0);
                $table->timestamps();
            });
        }

        // 7. gps_route_geometries
        if (!Schema::hasTable('gps_route_geometries')) {
            Schema::create('gps_route_geometries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('route_id')->index();
                $table->longText('polyline')->nullable();
                $table->json('waypoints_json')->nullable();
                $table->decimal('total_distance_km', 8, 2)->default(0.00);
                $table->integer('expected_duration_min')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // 8. gps_geofences
        if (!Schema::hasTable('gps_geofences')) {
            Schema::create('gps_geofences', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 32)->default('STOP'); // 'STOP', 'SCHOOL', 'DEPOT', 'CUSTOM'
                $table->string('shape', 32)->default('CIRCLE'); // 'CIRCLE', 'POLYGON'
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('radius_meters', 8, 2)->default(100.00);
                $table->json('coordinates_json')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // 9. gps_geofence_events
        if (!Schema::hasTable('gps_geofence_events')) {
            Schema::create('gps_geofence_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('geofence_id')->nullable()->index();
                $table->string('event_type', 64); // 'GEOFENCE_ENTER', 'GEOFENCE_EXIT', 'STOP_APPROACHING', 'STOP_ARRIVED', 'STOP_DEPARTED', 'SCHOOL_ENTERED', 'SCHOOL_EXITED'
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('speed', 6, 2)->default(0.00);
                $table->timestamp('timestamp')->useCurrent();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // 10. gps_boarding_events
        if (!Schema::hasTable('gps_boarding_events')) {
            Schema::create('gps_boarding_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('stop_id')->nullable()->index();
                $table->string('event_type', 32); // 'BOARDED', 'DROPPED', 'ABSENT', 'MANUAL_OVERRIDE'
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy', 8, 2)->nullable();
                $table->timestamp('timestamp')->useCurrent();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 11. gps_student_transport_assignments
        if (!Schema::hasTable('gps_student_transport_assignments')) {
            Schema::create('gps_student_transport_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->string('trip_type', 32)->default('BOTH'); // 'MORNING', 'EVENING', 'BOTH'
                $table->unsignedBigInteger('route_id')->index();
                $table->unsignedBigInteger('pickup_stop_id')->nullable();
                $table->unsignedBigInteger('drop_stop_id')->nullable();
                $table->date('active_from')->nullable();
                $table->date('active_to')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        // 12. gps_student_trip_attendances
        if (!Schema::hasTable('gps_student_trip_attendances')) {
            Schema::create('gps_student_trip_attendances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('pickup_stop_id')->nullable();
                $table->unsignedBigInteger('drop_stop_id')->nullable();
                $table->string('status', 32)->default('EXPECTED'); // 'EXPECTED', 'BOARDED', 'DROPPED', 'ABSENT', 'NOT_BOARDED'
                $table->timestamp('boarded_at')->nullable();
                $table->timestamp('dropped_at')->nullable();
                $table->string('override_reason')->nullable();
                $table->timestamps();

                $table->unique(['trip_id', 'student_id']);
            });
        }

        // 13. gps_alert_rules
        if (!Schema::hasTable('gps_alert_rules')) {
            Schema::create('gps_alert_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_type', 64)->unique(); // 'OVERSPEED', 'ROUTE_DEVIATION', 'LONG_STOP', 'GPS_OFFLINE', 'MOCK_LOCATION'
                $table->decimal('threshold_value', 10, 2);
                $table->integer('duration_seconds')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('notify_admin')->default(true);
                $table->boolean('notify_parents')->default(false);
                $table->timestamps();
            });
        }

        // 14. gps_alerts
        if (!Schema::hasTable('gps_alerts')) {
            Schema::create('gps_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->string('alert_type', 64);
                $table->string('severity', 32)->default('WARNING'); // 'INFO', 'WARNING', 'CRITICAL'
                $table->text('message');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('speed', 6, 2)->default(0.00);
                $table->boolean('is_resolved')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamps();
            });
        }

        // 15. gps_emergency_incidents
        if (!Schema::hasTable('gps_emergency_incidents')) {
            Schema::create('gps_emergency_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('incident_number', 64)->unique();
                $table->unsignedBigInteger('trip_id')->nullable()->index();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->string('emergency_type', 64)->default('SOS'); // 'SOS', 'ACCIDENT', 'MEDICAL_EMERGENCY', 'VEHICLE_BREAKDOWN', 'STUDENT_INCIDENT', 'SECURITY_INCIDENT', 'OTHER'
                $table->string('status', 32)->default('OPEN'); // 'OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'CANCELLED'
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->integer('student_count')->default(0);
                $table->text('description')->nullable();
                $table->timestamp('reported_at')->useCurrent();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamps();
            });
        }

        // 16. gps_emergency_actions
        if (!Schema::hasTable('gps_emergency_actions')) {
            Schema::create('gps_emergency_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id')->index();
                $table->string('action_type', 64); // 'CONTACT_DRIVER', 'NOTIFY_PARENTS', 'ASSIGN_REPLACEMENT_BUS', 'CLOSE_INCIDENT'
                $table->text('description')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->unsignedBigInteger('replacement_vehicle_id')->nullable();
                $table->unsignedBigInteger('replacement_driver_id')->nullable();
                $table->timestamps();
            });
        }

        // 17. gps_settings
        if (!Schema::hasTable('gps_settings')) {
            Schema::create('gps_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 128)->unique();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }

        // 18. Enhance existing vehicles table with GPS telemetry fields
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'registration_number')) {
                    $table->string('registration_number')->nullable()->after('vehicle_number');
                }
                if (!Schema::hasColumn('vehicles', 'vehicle_type')) {
                    $table->string('vehicle_type', 64)->default('Bus')->after('capacity');
                }
                if (!Schema::hasColumn('vehicles', 'location_source')) {
                    $table->string('location_source', 32)->default('MOBILE_APP')->after('status');
                }
                if (!Schema::hasColumn('vehicles', 'gps_tracking_enabled')) {
                    $table->boolean('gps_tracking_enabled')->default(true)->after('location_source');
                }
                if (!Schema::hasColumn('vehicles', 'device_imei')) {
                    $table->string('device_imei', 64)->nullable()->after('gps_tracking_enabled');
                }
                if (!Schema::hasColumn('vehicles', 'odometer_km')) {
                    $table->decimal('odometer_km', 10, 2)->default(0.00)->after('device_imei');
                }
                if (!Schema::hasColumn('vehicles', 'current_driver_id')) {
                    $table->unsignedBigInteger('current_driver_id')->nullable()->after('odometer_km');
                }
            });
        }

        // 19. Enhance existing pickup_points table with latitude, longitude, and geofence radius if missing
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
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_emergency_actions');
        Schema::dropIfExists('gps_emergency_incidents');
        Schema::dropIfExists('gps_alerts');
        Schema::dropIfExists('gps_alert_rules');
        Schema::dropIfExists('gps_student_trip_attendances');
        Schema::dropIfExists('gps_student_transport_assignments');
        Schema::dropIfExists('gps_boarding_events');
        Schema::dropIfExists('gps_geofence_events');
        Schema::dropIfExists('gps_geofences');
        Schema::dropIfExists('gps_route_geometries');
        Schema::dropIfExists('gps_trip_stops');
        Schema::dropIfExists('gps_trips');
        Schema::dropIfExists('vehicle_current_states');
        Schema::dropIfExists('vehicle_locations');
        Schema::dropIfExists('gps_sessions');
        Schema::dropIfExists('location_sources');
        Schema::dropIfExists('gps_settings');
    }
};
