<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\Route;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GpsTrip Model
 *
 * Implements Sections 14 & 15: Trip Architecture & States.
 * States: SCHEDULED -> READY -> ACTIVE -> COMPLETED (or PAUSED, CANCELLED, EMERGENCY).
 */
class GpsTrip extends Model
{
    use SoftDeletes;

    protected $table = 'gps_trips';

    protected $fillable = [
        'trip_number',
        'trip_type',             // 'MORNING' | 'EVENING' | 'SPECIAL'
        'vehicle_id',
        'driver_id',
        'route_id',
        'shift_id',
        'status',                // 'SCHEDULED', 'READY', 'ACTIVE', 'PAUSED', 'COMPLETED', 'CANCELLED', 'EMERGENCY'
        'scheduled_start_time',
        'actual_start_time',
        'actual_end_time',
        'distance_km',
        'duration_minutes',
        'total_students',
        'boarded_students',
        'dropped_students',
        'notes',
    ];

    protected $casts = [
        'distance_km'          => 'float',
        'duration_minutes'     => 'integer',
        'total_students'       => 'integer',
        'boarded_students'     => 'integer',
        'dropped_students'     => 'integer',
        'scheduled_start_time' => 'datetime',
        'actual_start_time'    => 'datetime',
        'actual_end_time'      => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function tripStops()
    {
        return $this->hasMany(GpsTripStop::class, 'trip_id')->orderBy('sequence');
    }

    public function studentAttendances()
    {
        return $this->hasMany(GpsStudentTripAttendance::class, 'trip_id');
    }

    public function alerts()
    {
        return $this->hasMany(GpsAlert::class, 'trip_id');
    }

    public function geofenceEvents()
    {
        return $this->hasMany(GpsGeofenceEvent::class, 'trip_id');
    }

    public function boardingEvents()
    {
        return $this->hasMany(GpsBoardingEvent::class, 'trip_id');
    }

    public function locations()
    {
        return $this->hasMany(VehicleLocation::class, 'trip_id')->orderBy('timestamp');
    }
}
