<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StudentAchievement extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'student_id',
        'class_section_id',
        'category',
        'competition_level',
        'title',
        'position',
        'award_date',
        'event_organizer',
        'certificate_media_url',
        'citation_notes',
        'is_featured_on_wall',
    ];

    protected $casts = [
        'award_date' => 'date',
        'is_featured_on_wall' => 'boolean',
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

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured_on_wall', true);
    }
}
