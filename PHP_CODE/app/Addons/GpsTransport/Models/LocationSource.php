<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * LocationSource Model
 *
 * Implements Section 62: location_sources table.
 * Supports MOBILE_APP (Phase 1) and GPS_DEVICE (Phase 2).
 */
class LocationSource extends Model
{
    protected $table = 'location_sources';

    protected $fillable = [
        'vehicle_id',
        'type',              // 'MOBILE_APP' | 'GPS_DEVICE'
        'source_identifier', // device UUID or IMEI
        'status',            // 'ACTIVE' | 'INACTIVE' | 'MAINTENANCE'
        'last_seen_at',
        'metadata',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'metadata'     => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
