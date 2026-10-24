<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AcademicLeaderboardSnapshot extends Model
{
    use HasFactory, DateFormatTrait;

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'exam_id',
        'class_id',
        'class_section_id',
        'student_id',
        'rank_in_section',
        'rank_in_class',
        'total_marks_obtained',
        'total_max_marks',
        'percentage',
        'grade',
        'calculated_at',
    ];

    protected $casts = [
        'rank_in_section' => 'integer',
        'rank_in_class' => 'integer',
        'total_marks_obtained' => 'decimal:2',
        'total_max_marks' => 'decimal:2',
        'percentage' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassSchool::class, 'class_id');
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
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

    public function scopeTopThree($query)
    {
        return $query->where('rank_in_section', '<=', 3);
    }
}
