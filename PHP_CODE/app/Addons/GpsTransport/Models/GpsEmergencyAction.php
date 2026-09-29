<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsEmergencyAction Model
 *
 * Implements Section 47: Replacement Vehicles and Audit Actions.
 */
class GpsEmergencyAction extends Model
{
    protected $table = 'gps_emergency_actions';

    protected $fillable = [
        'incident_id',
        'action_type',            // 'CONTACT_DRIVER', 'NOTIFY_PARENTS', 'ASSIGN_REPLACEMENT_BUS', 'CLOSE_INCIDENT'
        'description',
        'performed_by',
        'replacement_vehicle_id',
        'replacement_driver_id',
    ];

    public function incident()
    {
        return $this->belongsTo(GpsEmergencyIncident::class, 'incident_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function replacementVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'replacement_vehicle_id');
    }

    public function replacementDriver()
    {
        return $this->belongsTo(User::class, 'replacement_driver_id');
    }
}
