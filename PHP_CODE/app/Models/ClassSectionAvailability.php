<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ClassSectionAvailability extends Model
{
    use DateFormatTrait;

    protected $table = 'class_section_availability';

    protected $fillable = [
        'school_id',
        'class_section_id',
        'day',
        'period_id',
        'status',
        'reason'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id')->withTrashed();
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
