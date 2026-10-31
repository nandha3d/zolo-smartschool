<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RouteVehicle extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'helper_id',
        'shift_id',
        'status',
        'created_at',
        'updated_at',
        'history_id',
        'pickup_start_time',
        'pickup_end_time',
        'drop_start_time',
        'drop_end_time',
    ];
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class);
    }

    public function helper()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function transportationPayments()
    {
        return $this->hasMany(TransportationPayment::class, 'route_vehicle_id');
    }

    public function lastPickupPoint()
    {
        return $this->belongsTo(PickupPoint::class, 'last_pickup_point_id');
    }

    public function alternateDrivers()
    {
        return $this->hasMany(RouteVehicleAlternateDriver::class);
    }

    /**
     * Route vehicles the user may operate on $date: as helper, as the primary driver when
     * no relief driver has taken the run over, or as a rostered relief driver.
     * $run is 'pickup', 'drop' or null (either run).
     */
    public function scopeOperatedBy($query, int $userId, $date = null, ?string $run = null)
    {
        return $query->where(function ($q) use ($userId, $date, $run) {
            $q->where('helper_id', $userId)
                ->orWhere(function ($primary) use ($userId, $date, $run) {
                    $primary->where('driver_id', $userId)
                        ->whereDoesntHave('alternateDrivers', function ($sub) use ($date, $run) {
                            $sub->inForce($date, $run);
                            // With no run given, a pickup-only or drop-only relief still
                            // leaves the primary driver the other run.
                            if ($run === null) {
                                $sub->where('shift_type', RouteVehicleAlternateDriver::SHIFT_ALL);
                            }
                        });
                })
                ->orWhereHas('alternateDrivers', function ($sub) use ($userId, $date, $run) {
                    $sub->inForce($date, $run)->where('alternate_driver_id', $userId);
                });
        });
    }

    /**
     * Who drives this vehicle on $date for the run: the relief driver if one is rostered,
     * otherwise the primary driver.
     */
    public function effectiveDriverId($date = null, ?string $run = null): ?int
    {
        $relief = $this->alternateDrivers()->inForce($date, $run)->latest('id')->value('alternate_driver_id');
        return $relief ? (int) $relief : ($this->driver_id ? (int) $this->driver_id : null);
    }
}
