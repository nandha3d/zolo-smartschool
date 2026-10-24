<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PeriodAttendance extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'class_section_id',
        'student_id',
        'date',
        'timetable_period_id',
        'period_number',
        'subject_id',
        'marked_by_user_id',
        'status',
        'minutes_late',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'period_number' => 'integer',
        'minutes_late' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function period()
    {
        return $this->belongsTo(TimetablePeriod::class, 'timetable_period_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function markedByUser()
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }
}
