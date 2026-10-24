<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StudentLeadershipRole extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'class_section_id',
        'student_id',
        'role_type',
        'title',
        'badge_icon',
        'badge_color',
        'assigned_by',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function sessionYear()
    {
        return $this->belongsTo(SessionYear::class, 'session_year_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
