<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SubjectTeacherAlternate extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'school_id',
        'subject_teacher_id',
        'alternate_teacher_id',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'priority'  => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function subject_teacher()
    {
        return $this->belongsTo(SubjectTeacher::class, 'subject_teacher_id');
    }

    public function alternate_teacher()
    {
        return $this->belongsTo(User::class, 'alternate_teacher_id')->withTrashed();
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
