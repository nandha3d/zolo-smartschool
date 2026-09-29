<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TimetableGenerationRun extends Model
{
    use DateFormatTrait;

    protected $fillable = [
        'school_id',
        'session_year_id',
        'semester_id',
        'template_id',
        'started_by',
        'status',
        'algorithm_version',
        'input_hash',
        'started_at',
        'completed_at',
        'error_message',
        'statistics'
    ];

    protected $casts = [
        'statistics'   => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function template()
    {
        return $this->belongsTo(TimetableTemplate::class, 'template_id');
    }

    public function session_year()
    {
        return $this->belongsTo(SessionYear::class, 'session_year_id')->withTrashed();
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    public function entries()
    {
        return $this->hasMany(TimetableGenerationEntry::class, 'generation_run_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
