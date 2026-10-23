<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimetableResource extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'block',
        'capacity',
        'description',
        'is_active'
    ];

    protected $casts = [
        'capacity'  => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function availabilities()
    {
        return $this->hasMany(TimetableResourceAvailability::class, 'resource_id');
    }

    public function requirements()
    {
        return $this->hasMany(SubjectScheduleRequirement::class, 'resource_id');
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'resource_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
