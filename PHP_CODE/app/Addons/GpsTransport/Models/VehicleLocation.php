<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * VehicleLocation Model
 *
 * Implements Section 11: vehicle_locations table.
 * Audit breadcrumbs of all normalized telemetry points.
 */
class VehicleLocation extends Model
{
    protected $table = 'vehicle_locations';

    protected $fillable = [
        'vehicle_id',
        'driver_id',
        'trip_id',
        'source_type',       // 'MOBILE_APP' | 'GPS_DEVICE'
        'source_id',
        'latitude',
        'longitude',
        'accuracy',
        'altitude',
        'speed',
        'heading',
        'timestamp',
        'received_at',
        'battery_level',
        'is_mock_location',
        'network_type',
        'flags',
    ];

    protected $casts = [
        'latitude'         => 'float',
        'longitude'        => 'float',
        'accuracy'         => 'float',
        'altitude'         => 'float',
        'speed'            => 'float',
        'heading'          => 'float',
        'timestamp'        => 'datetime',
        'received_at'      => 'datetime',
        'battery_level'    => 'integer',
        'is_mock_location' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }
}
