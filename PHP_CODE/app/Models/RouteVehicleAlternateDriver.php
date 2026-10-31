<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A relief driver rostered on a route vehicle for a date range (and optionally only the
 * pickup or the drop run). While it is in force the alternate driver operates the vehicle
 * and the primary driver is locked out of it for that run.
 */
class RouteVehicleAlternateDriver extends Model
{
    public const SHIFT_ALL = 'ALL';
    public const SHIFT_PICKUP = 'PICKUP_ONLY';
    public const SHIFT_DROP = 'DROP_ONLY';

    protected $fillable = [
        'school_id',
        'route_vehicle_id',
        'primary_driver_id',
        'alternate_driver_id',
        'start_date',
        'end_date',
        'shift_type',
        'reason',
        'notify_parents',
        'parents_notified_at',
        'assigned_by',
        'status',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date'          => 'date',
        'end_date'            => 'date',
        'notify_parents'      => 'boolean',
        'parents_notified_at' => 'datetime',
        'cancelled_at'        => 'datetime',
    ];

    public function routeVehicle()
    {
        return $this->belongsTo(RouteVehicle::class)->withTrashed();
    }

    public function primaryDriver()
    {
        return $this->belongsTo(User::class, 'primary_driver_id')->withTrashed();
    }

    public function alternateDriver()
    {
        return $this->belongsTo(User::class, 'alternate_driver_id')->withTrashed();
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    /**
     * Rows in force on $date. $run is 'pickup', 'drop' or null for "either run".
     */
    public function scopeInForce(Builder $query, $date = null, ?string $run = null): Builder
    {
        $day = Carbon::parse($date ?? Carbon::today())->toDateString();
        $query->where('status', '!=', 'CANCELLED')
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day);

        if ($run === 'pickup') {
            $query->whereIn('shift_type', [self::SHIFT_ALL, self::SHIFT_PICKUP]);
        } elseif ($run === 'drop') {
            $query->whereIn('shift_type', [self::SHIFT_ALL, self::SHIFT_DROP]);
        }

        return $query;
    }

    /**
     * Display status: cancelled, upcoming, active or completed.
     */
    public function getStateAttribute(): string
    {
        if ($this->status === 'CANCELLED') {
            return 'CANCELLED';
        }
        $today = Carbon::today();
        if ($this->start_date->gt($today)) {
            return 'UPCOMING';
        }
        return $this->end_date->lt($today) ? 'COMPLETED' : 'ACTIVE';
    }
}
