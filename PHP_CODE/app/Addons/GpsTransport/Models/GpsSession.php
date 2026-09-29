<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsSession Model
 *
 * Implements Section 63: gps_sessions table.
 */
class GpsSession extends Model
{
    protected $table = 'gps_sessions';

    protected $fillable = [
        'trip_id',
        'vehicle_id',
        'driver_id',
        'location_source_id',
        'started_at',
        'ended_at',
        'last_location_at',
        'status', // 'ACTIVE' | 'PAUSED' | 'ENDED'
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'last_location_at' => 'datetime',
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

    public function locationSource()
    {
        return $this->belongsTo(LocationSource::class, 'location_source_id');
    }
}
