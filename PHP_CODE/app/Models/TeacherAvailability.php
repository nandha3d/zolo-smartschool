<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TeacherAvailability extends Model
{
    use DateFormatTrait;

    protected $table = 'teacher_availability';

    protected $fillable = [
        'school_id',
        'teacher_id',
        'day',
        'period_id',
        'status',
        'reason'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
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

    public function scopePreferred($query)
    {
        return $query->where('status', 'preferred');
    }
}
