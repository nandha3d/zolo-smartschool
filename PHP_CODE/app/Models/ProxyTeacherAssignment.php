<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A teacher covering one timetable slot of an absent colleague on one date.
 */
class ProxyTeacherAssignment extends Model
{
    protected $fillable = [
        'school_id',
        'date',
        'timetable_id',
        'class_section_id',
        'subject_id',
        'absent_teacher_id',
        'proxy_teacher_id',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function timetable()
    {
        return $this->belongsTo(Timetable::class);
    }

    public function classSection()
    {
        return $this->belongsTo(ClassSection::class)->withTrashed();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function absentTeacher()
    {
        return $this->belongsTo(User::class, 'absent_teacher_id')->withTrashed();
    }

    public function proxyTeacher()
    {
        return $this->belongsTo(User::class, 'proxy_teacher_id')->withTrashed();
    }
}
