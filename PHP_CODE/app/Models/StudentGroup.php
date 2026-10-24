<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StudentGroup extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'class_section_id',
        'name',
        'type',
        'description',
        'leader_student_id',
        'vice_leader_student_id',
        'color_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members()
    {
        return $this->hasMany(StudentGroupMember::class, 'student_group_id');
    }

    public function students()
    {
        return $this->belongsToMany(Students::class, 'student_group_members', 'student_group_id', 'student_id')
                    ->withPivot('bench_number', 'role_in_group', 'joined_at')
                    ->withTimestamps();
    }

    public function leader()
    {
        return $this->belongsTo(Students::class, 'leader_student_id');
    }

    public function viceLeader()
    {
        return $this->belongsTo(Students::class, 'vice_leader_student_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
