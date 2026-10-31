<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\DateFormatTrait;

class PickupPoint extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'geofence_radius',
        'halt_duration_minutes',
        'landmark',
        'contact_person',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'halt_duration_minutes' => 'integer',
    ];

    /**
     * Get the route pickup points for this pickup point.
     */
    public function routePickupPoints()
    {
        return $this->hasMany(RoutePickupPoint::class);
    }

    /**
     * Get the routes associated with this pickup point.
     */
    public function routes()
    {
        return $this->belongsToMany(Route::class, 'route_pickup_points')
                    ->withPivot('order', 'pickup_time', 'drop_time')
                    ->withTimestamps()
                    ->orderBy('pivot_order');
    }

    /**
     * Get the transportation fees for this pickup point.
     */
    public function transportationFees()
    {
        return $this->hasMany(TransportationFee::class);
    }

    /**
     * Scope a query to only include active pickup points.
     */
    
     public function getCreatedAtAttribute($value)
     {
         return $this->formatDateValue($value);
     }

     public function getUpdatedAtAttribute($value)
     {
        return  $this->formatDateValue($value);
     }
}