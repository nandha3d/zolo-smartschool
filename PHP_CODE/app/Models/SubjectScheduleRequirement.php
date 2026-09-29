<?php

namespace App\Models;

use App\Services\CachingService;
use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SubjectScheduleRequirement extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'school_id',
        'class_section_id',
        'subject_id',
        'class_subject_id',
        'semester_id',
        'weekly_periods',
        'min_periods_per_day',
        'max_periods_per_day',
        'allow_consecutive',
        'required_consecutive_periods',
        'preferred_day',
        'preferred_period_start',
        'preferred_period_end',
        'avoid_first_period',
        'avoid_last_period',
        'priority',
        'resource_id',
        'resource_required',
        'is_active'
    ];

    protected $casts = [
        'weekly_periods'               => 'integer',
        'min_periods_per_day'          => 'integer',
        'max_periods_per_day'          => 'integer',
        'allow_consecutive'            => 'boolean',
        'required_consecutive_periods' => 'integer',
        'avoid_first_period'           => 'boolean',
        'avoid_last_period'            => 'boolean',
        'priority'                     => 'integer',
        'resource_required'            => 'boolean',
        'is_active'                    => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class)->withTrashed();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function class_subject()
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class)->withTrashed();
    }

    public function resource()
    {
        return $this->belongsTo(TimetableResource::class, 'resource_id');
    }

    public function subject_teachers()
    {
        return $this->hasMany(SubjectTeacher::class, 'class_section_id', 'class_section_id')
            ->whereColumn('subject_id', 'subject_schedule_requirements.subject_id');
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

    public function scopeCurrentSemesterData($query)
    {
        $currentSemester = app(CachingService::class)->getDefaultSemesterData();
        if ($currentSemester) {
            $query->where(function ($q) use ($currentSemester) {
                $q->where('semester_id', $currentSemester->id)->orWhereNull('semester_id');
            });
        }
        return $query;
    }
}
