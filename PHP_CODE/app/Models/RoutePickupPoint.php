<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutePickupPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'pickup_point_id',
        'pickup_time',
        'drop_time',
        'order',
        'morning_order',
        'afternoon_order',
        'is_active',
    ];

    protected $casts = [
        'order' => 'integer',
        'morning_order' => 'integer',
        'afternoon_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Active stops of a route in the order the bus visits them: the morning (pickup) order,
     * or the afternoon (drop) order, which defaults to the morning route in reverse.
     * Stops saved by the older route form only have `order`, so that is the fallback.
     */
    public static function forTrip(int $routeId, string $tripType, bool $includeInactive = false)
    {
        $stops = static::with('pickupPoint')
            ->where('route_id', $routeId)
            ->when(!$includeInactive, fn ($q) => $q->where(fn ($q2) => $q2->where('is_active', true)->orWhereNull('is_active')))
            ->get();

        $morning = $stops->sortBy(fn ($s) => [$s->morning_order ?? $s->order, $s->id])->values();
        if (strtoupper($tripType) !== 'EVENING') {
            return $morning;
        }

        $reversed = $morning->reverse()->values();
        return $stops->sortBy(function ($s) use ($reversed) {
            $fallback = $reversed->search(fn ($r) => $r->id === $s->id) + 1;
            return [$s->afternoon_order ?? $fallback, $s->id];
        })->values();
    }

    /**
     * Get the route that owns this route pickup point.
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get the pickup point that belongs to this route pickup point.
     */
    public function pickupPoint()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    /**
     * Scope a query to order by the order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
    
}