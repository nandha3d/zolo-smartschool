<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsEmergencyIncident Model
 *
 * Implements Sections 44 & 45: Emergency Center & Incident Tracking.
 * Types: SOS, ACCIDENT, MEDICAL_EMERGENCY, VEHICLE_BREAKDOWN, STUDENT_INCIDENT, SECURITY_INCIDENT, OTHER.
 */
class GpsEmergencyIncident extends Model
{
    protected $table = 'gps_emergency_incidents';

    protected $fillable = [
        'incident_number',
        'trip_id',
        'vehicle_id',
        'driver_id',
        'emergency_type',    // 'SOS', 'ACCIDENT', 'MEDICAL_EMERGENCY', 'VEHICLE_BREAKDOWN', 'STUDENT_INCIDENT', 'SECURITY_INCIDENT', 'OTHER'
        'status',            // 'OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'CANCELLED'
        'latitude',
        'longitude',
        'student_count',
        'description',
        'reported_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'latitude'      => 'float',
        'longitude'     => 'float',
        'student_count' => 'integer',
        'reported_at'   => 'datetime',
        'resolved_at'   => 'datetime',
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

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function actions()
    {
        return $this->hasMany(GpsEmergencyAction::class, 'incident_id')->orderBy('created_at');
    }
}
