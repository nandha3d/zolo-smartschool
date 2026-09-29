<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\PickupPoint;
use App\Models\Students;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsBoardingEvent Model
 *
 * Implements Section 32: Immutable Boarding Events.
 * Types: BOARDED, DROPPED, ABSENT, MANUAL_OVERRIDE.
 */
class GpsBoardingEvent extends Model
{
    protected $table = 'gps_boarding_events';

    protected $fillable = [
        'trip_id',
        'vehicle_id',
        'driver_id',
        'student_id',
        'stop_id',
        'event_type',       // 'BOARDED', 'DROPPED', 'ABSENT', 'MANUAL_OVERRIDE'
        'latitude',
        'longitude',
        'accuracy',
        'timestamp',
        'notes',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'accuracy'  => 'float',
        'timestamp' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function stop()
    {
        return $this->belongsTo(PickupPoint::class, 'stop_id');
    }
}
