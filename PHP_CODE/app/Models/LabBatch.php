<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LabBatch extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'class_section_id',
        'resource_id',
        'name',
        'batch_code',
        'max_capacity',
        'batch_leader_student_id',
        'lab_assistant_staff_id',
        'status',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
    ];

    public function resource()
    {
        return $this->belongsTo(TimetableResource::class, 'resource_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function sessionYear()
    {
        return $this->belongsTo(SessionYear::class, 'session_year_id');
    }

    public function batchLeader()
    {
        return $this->belongsTo(Students::class, 'batch_leader_student_id');
    }

    public function labAssistant()
    {
        return $this->belongsTo(Staff::class, 'lab_assistant_staff_id');
    }

    public function batchStudents()
    {
        return $this->hasMany(LabBatchStudent::class, 'lab_batch_id');
    }

    public function students()
    {
        return $this->belongsToMany(Students::class, 'lab_batch_students', 'lab_batch_id', 'student_id')
                    ->withPivot('workstation_number')
                    ->withTimestamps();
    }

    public function sessions()
    {
        return $this->hasMany(LabSession::class, 'lab_batch_id');
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
        return $query->where('status', 'active');
    }
}
