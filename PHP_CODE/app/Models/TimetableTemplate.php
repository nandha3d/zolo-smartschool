<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TimetableTemplate extends Model
{
    use SoftDeletes, DateFormatTrait;

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'working_days',
        'is_default',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function periods()
    {
        return $this->hasMany(TimetablePeriod::class, 'timetable_template_id')->orderBy('period_number', 'ASC');
    }

    public function instructional_periods()
    {
        return $this->hasMany(TimetablePeriod::class, 'timetable_template_id')
            ->where('is_instructional', true)
            ->where('is_active', true)
            ->orderBy('period_number', 'ASC');
    }

    public function generation_runs()
    {
        return $this->hasMany(TimetableGenerationRun::class, 'template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
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
