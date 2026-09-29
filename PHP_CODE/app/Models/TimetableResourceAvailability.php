<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimetableResourceAvailability extends Model
{
    use DateFormatTrait;

    protected $table = 'timetable_resource_availability';

    protected $fillable = [
        'school_id',
        'resource_id',
        'day',
        'period_id',
        'status',
        'reason'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function resource()
    {
        return $this->belongsTo(TimetableResource::class, 'resource_id');
    }

    public function period()
    {
        return $this->belongsTo(TimetablePeriod::class, 'period_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeUnavailable($query)
    {
        return $query->where('status', 'unavailable');
    }
}
