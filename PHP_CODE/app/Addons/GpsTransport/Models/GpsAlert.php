<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsAlert Model
 *
 * Implements Section 42: Driver Safety Events & Alerts.
 */
class GpsAlert extends Model
{
    protected $table = 'gps_alerts';

    protected $fillable = [
        'trip_id',
        'vehicle_id',
        'alert_type',       // 'OVERSPEED', 'ROUTE_DEVIATION', 'LONG_STOP', 'GPS_OFFLINE', 'MOCK_LOCATION_WARNING'
        'severity',         // 'INFO', 'WARNING', 'CRITICAL'
        'message',
        'latitude',
        'longitude',
        'speed',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'driver_id',
        'route_id',
        'speed_limit',
        'peak_speed',
        'duration_seconds',
        'acknowledged_by',
        'acknowledged_at',
        'admin_remarks',
    ];

    protected $casts = [
        'latitude'         => 'float',
        'longitude'        => 'float',
        'speed'            => 'float',
        'speed_limit'      => 'float',
        'peak_speed'       => 'float',
        'duration_seconds' => 'integer',
        'is_resolved'      => 'boolean',
        'resolved_at'      => 'datetime',
        'acknowledged_at'  => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }

    public function route()
    {
        return $this->belongsTo(\App\Models\Route::class, 'route_id');
    }

    public function acknowledger()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
