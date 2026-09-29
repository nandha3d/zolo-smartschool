<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\PickupPoint;
use App\Models\Students;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsStudentTripAttendance Model
 *
 * Implements Sections 31 & 48: Trip Roster & Child Still Onboard Safety Check.
 * Statuses: EXPECTED, BOARDED, DROPPED, ABSENT, NOT_BOARDED.
 */
class GpsStudentTripAttendance extends Model
{
    protected $table = 'gps_student_trip_attendances';

    protected $fillable = [
        'trip_id',
        'student_id',
        'pickup_stop_id',
        'drop_stop_id',
        'status',           // 'EXPECTED', 'BOARDED', 'DROPPED', 'ABSENT', 'NOT_BOARDED'
        'boarded_at',
        'dropped_at',
        'override_reason',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
        'dropped_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(GpsTrip::class, 'trip_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
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
