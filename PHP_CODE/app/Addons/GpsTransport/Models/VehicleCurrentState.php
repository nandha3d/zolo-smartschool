<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\PickupPoint;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * VehicleCurrentState Model
 *
 * Implements Section 21 & Section 64: vehicle_current_states table.
 * Fast single-row per vehicle for real-time live map queries without scanning millions of historical points.
 */
class VehicleCurrentState extends Model
{
    protected $table = 'vehicle_current_states';
    protected $primaryKey = 'vehicle_id';
    public $incrementing = false;

    protected $fillable = [
        'vehicle_id',
        'trip_id',
        'driver_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'status',           // 'MOVING', 'STOPPED', 'OFFLINE', 'ON_TRIP', 'AT_SCHOOL', 'EMERGENCY', 'BREAKDOWN'
        'next_stop_id',
        'eta',
        'gps_status',       // 'CONNECTED', 'STALE', 'OFFLINE'
        'battery_level',
        'is_mock_location',
        'network_type',
        'last_location_at',
    ];

    protected $casts = [
        'latitude'         => 'float',
        'longitude'        => 'float',
        'speed'            => 'float',
        'heading'          => 'float',
        'battery_level'    => 'integer',
        'is_mock_location' => 'boolean',
        'last_location_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function nextStop()
    {
        return $this->belongsTo(PickupPoint::class, 'next_stop_id');
    }
}
