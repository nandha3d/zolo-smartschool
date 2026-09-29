<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\PickupPoint;
use App\Models\Route;
use App\Models\Students;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsStudentTransportAssignment Model
 *
 * Implements Section 30: Student Transport Assignment.
 * Independent morning/evening assignments.
 */
class GpsStudentTransportAssignment extends Model
{
    protected $table = 'gps_student_transport_assignments';

    protected $fillable = [
        'student_id',
        'trip_type',       // 'MORNING', 'EVENING', 'BOTH'
        'route_id',
        'pickup_stop_id',
        'drop_stop_id',
        'active_from',
        'active_to',
        'status',          // 1: active, 0: inactive
    ];

    protected $casts = [
        'active_from' => 'date',
        'active_to'   => 'date',
        'status'      => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function pickupStop()
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_stop_id');
    }

    public function dropStop()
    {
        return $this->belongsTo(PickupPoint::class, 'drop_stop_id');
    }
}
