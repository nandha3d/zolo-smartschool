<?php

namespace App\Addons\GpsTransport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GpsGeofence Model
 *
 * Implements Section 27: Geofencing.
 * Types: STOP, SCHOOL, DEPOT, CUSTOM
 */
class GpsGeofence extends Model
{
    protected $table = 'gps_geofences';

    protected $fillable = [
        'name',
        'type',             // 'STOP', 'SCHOOL', 'DEPOT', 'CUSTOM'
        'shape',            // 'CIRCLE', 'POLYGON'
        'latitude',
        'longitude',
        'radius_meters',
        'coordinates_json',
        'active',
    ];

    protected $casts = [
        'latitude'         => 'float',
        'longitude'        => 'float',
        'radius_meters'    => 'float',
        'coordinates_json' => 'array',
        'active'           => 'boolean',
    ];

    public function events()
    {
        return $this->hasMany(GpsGeofenceEvent::class, 'geofence_id');
    }
}
