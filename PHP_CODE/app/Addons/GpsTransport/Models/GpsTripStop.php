<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\PickupPoint;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsTripStop Model
 *
 * Implements Sections 23 & 29: Trip Stops and Stop State Machine.
 * States: UPCOMING, APPROACHING, ARRIVED, WAITING, DEPARTED, SKIPPED.
 */
class GpsTripStop extends Model
{
    protected $table = 'gps_trip_stops';

    protected $fillable = [
        'trip_id',
        'stop_id',
        'sequence',
        'planned_arrival_time',
        'planned_departure_time',
        'actual_arrival_time',
        'actual_departure_time',
        'status',                // 'UPCOMING', 'APPROACHING', 'ARRIVED', 'WAITING', 'DEPARTED', 'SKIPPED'
        'expected_board',
        'actual_board',
        'expected_drop',
        'actual_drop',
    ];

    protected $casts = [
        'sequence'       => 'integer',
        'expected_board' => 'integer',
        'actual_board'   => 'integer',
        'expected_drop'  => 'integer',
        'actual_drop'    => 'integer',
    ];

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    public function stop()
    {
        return $this->belongsTo(PickupPoint::class, 'stop_id');
    }
}
