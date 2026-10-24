<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LabSession extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'lab_batch_id',
        'subject_id',
        'timetable_id',
        'session_date',
        'experiment_title',
        'conducted_by_teacher_id',
        'batch_leader_verified',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'batch_leader_verified' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(LabBatch::class, 'lab_batch_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function timetable()
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    public function conductedByTeacher()
    {
        return $this->belongsTo(Staff::class, 'conducted_by_teacher_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
