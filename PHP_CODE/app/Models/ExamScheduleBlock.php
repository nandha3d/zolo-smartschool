<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ExamScheduleBlock extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'exam_id',
        'class_id',
        'class_section_id',
        'date',
        'block_type',
        'subject_id',
        'start_period_id',
        'end_period_id',
        'custom_start_time',
        'custom_end_time',
        'exam_incharge_staff_id',
        'assistant_incharge_staff_id',
        'hall_or_resource_id',
        'total_marks',
        'passing_marks',
        'instructions',
    ];

    protected $casts = [
        'date' => 'date',
        'total_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassSchool::class, 'class_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function startPeriod()
    {
        return $this->belongsTo(TimetablePeriod::class, 'start_period_id');
    }

    public function endPeriod()
    {
        return $this->belongsTo(TimetablePeriod::class, 'end_period_id');
    }

    public function examIncharge()
    {
        return $this->belongsTo(Staff::class, 'exam_incharge_staff_id');
    }

    public function assistantIncharge()
    {
        return $this->belongsTo(Staff::class, 'assistant_incharge_staff_id');
    }

    public function hallResource()
    {
        return $this->belongsTo(TimetableResource::class, 'hall_or_resource_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
