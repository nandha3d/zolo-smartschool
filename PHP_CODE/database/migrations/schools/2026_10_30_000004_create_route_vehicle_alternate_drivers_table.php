<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relief drivers: while a row is in force, the alternate driver operates the route
 * vehicle and the primary driver loses access to it for that shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('route_vehicle_alternate_drivers')) {
            return;
        }

        Schema::create('route_vehicle_alternate_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->foreignId('route_vehicle_id')->constrained('route_vehicles')->cascadeOnDelete();
            $table->unsignedBigInteger('primary_driver_id')->nullable();
            $table->foreignId('alternate_driver_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('shift_type', 16)->default('ALL'); // ALL | PICKUP_ONLY | DROP_ONLY
            $table->string('reason', 255);
            $table->boolean('notify_parents')->default(true);
            $table->timestamp('parents_notified_at')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->string('status', 16)->default('SCHEDULED'); // SCHEDULED | CANCELLED
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['route_vehicle_id', 'start_date', 'end_date'], 'rvad_lookup_idx');
            $table->index(['alternate_driver_id', 'start_date', 'end_date'], 'rvad_driver_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_vehicle_alternate_drivers');
    }
};
