<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;

class TimetableGenerationEntry extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'generation_run_id',
        'class_section_id',
        'subject_id',
        'subject_teacher_id',
        'teacher_id',
        'resource_id',
        'day',
        'period_id',
        'start_time',
        'end_time',
        'type',
        'is_locked',
        'constraint_score',
        'explanation'
    ];

    protected $casts = [
        'is_locked'        => 'boolean',
        'constraint_score' => 'integer',
        'explanation'      => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function generation_run()
    {
        return $this->belongsTo(TimetableGenerationRun::class, 'generation_run_id');
    }

    public function class_section()
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id')->withTrashed();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }

    public function subject_teacher()
    {
        return $this->belongsTo(SubjectTeacher::class, 'subject_teacher_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function resource()
    {
        return $this->belongsTo(TimetableResource::class, 'resource_id');
    }

    public function period()
    {
        return $this->belongsTo(TimetablePeriod::class, 'period_id');
    }
}
