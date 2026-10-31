<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'vehicle_number',
        'capacity',
        'speed_limit_kmh',
        'speed_alert_cooldown_minutes',
        'status',
    ];

    public function routeVehicles()
    {
        return $this->hasMany(RouteVehicle::class, 'vehicle_id');
    }
}
