<?php

namespace App\Addons\GpsTransport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GpsAlertRule Model
 *
 * Implements Section 66: Alert Rule Engine.
 */
class GpsAlertRule extends Model
{
    protected $table = 'gps_alert_rules';

    protected $fillable = [
        'rule_type',          // 'OVERSPEED', 'ROUTE_DEVIATION', 'LONG_STOP', 'GPS_OFFLINE', 'MOCK_LOCATION'
        'threshold_value',
        'duration_seconds',
        'is_active',
        'notify_admin',
        'notify_parents',
    ];

    protected $casts = [
        'threshold_value'  => 'float',
        'duration_seconds' => 'integer',
        'is_active'        => 'boolean',
        'notify_admin'     => 'boolean',
        'notify_parents'   => 'boolean',
    ];
}
