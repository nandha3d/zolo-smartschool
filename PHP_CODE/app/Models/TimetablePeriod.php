<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimetablePeriod extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'school_id',
        'timetable_template_id',
        'period_number',
        'name',
        'start_time',
        'end_time',
        'type',
        'is_instructional',
        'is_active'
    ];

    protected $casts = [
        'period_number'    => 'integer',
        'is_instructional' => 'boolean',
        'is_active'        => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function template()
    {
        return $this->belongsTo(TimetableTemplate::class, 'timetable_template_id');
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'period_id');
    }

    public function teacher_availabilities()
    {
        return $this->hasMany(TeacherAvailability::class, 'period_id');
    }

    public function class_availabilities()
    {
        return $this->hasMany(ClassSectionAvailability::class, 'period_id');
    }

    public function resource_availabilities()
    {
        return $this->hasMany(TimetableResourceAvailability::class, 'period_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeInstructional($query)
    {
        return $query->where('is_instructional', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
