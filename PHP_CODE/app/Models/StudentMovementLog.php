<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StudentMovementLog extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'student_id',
        'date',
        'movement_type',
        'departure_time',
        'expected_return_time',
        'actual_return_time',
        'authorized_by_staff_id',
        'pickup_person_name',
        'pickup_person_relationship',
        'pickup_person_phone',
        'reason',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function authorizedByStaff()
    {
        return $this->belongsTo(Staff::class, 'authorized_by_staff_id');
    }

    public function sessionYear()
    {
        return $this->belongsTo(SessionYear::class, 'session_year_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeCurrentlyOut($query)
    {
        return $query->where('status', 'out');
    }
}
