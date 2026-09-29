<?php

namespace App\Addons\GpsTransport\Models;

use App\Models\Route;
use Illuminate\Database\Eloquent\Model;

/**
 * GpsRouteGeometry Model
 *
 * Implements Section 26: Route Polyline and Geometry Storage.
 * Required for route visualization and route deviation detection.
 */
class GpsRouteGeometry extends Model
{
    protected $table = 'gps_route_geometries';

    protected $fillable = [
        'route_id',
        'polyline',
        'waypoints_json',
        'total_distance_km',
        'expected_duration_min',
        'active',
    ];

    protected $casts = [
        'waypoints_json'        => 'array',
        'total_distance_km'     => 'float',
        'expected_duration_min' => 'integer',
        'active'                => 'boolean',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }
}
