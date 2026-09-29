<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsGeofenceEvent Model
 *
 * Implements Section 28: Geofence Events.
 * Event types: GEOFENCE_ENTER, GEOFENCE_EXIT, STOP_APPROACHING, STOP_ARRIVED, STOP_DEPARTED, SCHOOL_ENTERED, SCHOOL_EXITED.
 */
class GpsGeofenceEvent extends Model
{
    protected $table = 'gps_geofence_events';

    protected $fillable = [
        'trip_id',
        'vehicle_id',
        'geofence_id',
        'event_type',
        'latitude',
        'longitude',
        'speed',
        'timestamp',
        'description',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'speed'     => 'float',
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

    public function geofence()
    {
        return $this->belongsTo(GpsGeofence::class, 'geofence_id');
    }
}
