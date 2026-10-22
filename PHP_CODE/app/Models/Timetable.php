<?php

namespace App\Models;

use App\Repositories\Semester\SemesterInterface;
use App\Services\CachingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;

class Timetable extends Model {
    use DateFormatTrait;
    
    protected $fillable = [
        "subject_teacher_id",
        "class_section_id",
        "subject_id",
        "start_time",
        "end_time",
        "note",
        "day",
        "type",
        "is_locked",
        "period_id",
        "resource_id",
        "locked_by",
        "locked_at",
        "lock_reason",
        "semester_id",
        "school_id"
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected $appends = ['title', 'meta', 'primary_teacher_name', 'alternate_teacher_name', 'period_no'];
    protected $hidden = ['created_at','updated_at'];


    public function subject_teacher() {
        return $this->belongsTo(SubjectTeacher::class);
    }

    public function class_section() {
        return $this->belongsTo(ClassSection::class)->withTrashed();
    }

    public function subject() {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function teacher() {
        return $this->hasOneThrough(User::class, SubjectTeacher::class, 'id', 'id', 'subject_teacher_id', 'teacher_id')->withTrashed();
    }

    public function period() {
        return $this->belongsTo(TimetablePeriod::class, 'period_id');
    }

    public function resource() {
        return $this->belongsTo(TimetableResource::class, 'resource_id');
    }

    public function locked_by_user() {
        return $this->belongsTo(User::class, 'locked_by')->withTrashed();
    }

    public function scopeOwner($query) {
        if (Auth::user()) {
            $user = Auth::user();
            if ($user->hasRole('School Admin')) {
                $cache = app(CachingService::class);
                $query->with(['session_years_trackings' => function ($q) use ($cache) {
                    $q->where('session_year_id', $cache->getDefaultSessionYear()->id);
                }]);
                return $this->where('school_id', $user->school_id);
            }

            if (Auth::user()->hasRole('Student')) {
                return $query->where('school_id', Auth::user()->school_id);
            }
    //        if ($user->hasRole('Teacher')) {
    //            $teacher_id = $user->teacher()->pluck('id');
    //            return $query->whereIn('teacher_id', $teacher_id);
    //        }
            if (Auth::user()->school_id) {
                return $this->where('school_id', $user->school_id);
            }
        }
        return $query;
    }

    public function getMetaAttribute() {
        if (!empty($this->note) && str_starts_with(trim($this->note), '{')) {
            $data = json_decode($this->note, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    public function getPrimaryTeacherNameAttribute() {
        if ($this->relationLoaded('teacher') && $this->teacher) {
            return $this->teacher->full_name;
        }
        $meta = $this->meta;
        if (!empty($meta['primary_teacher_name'])) {
            return $meta['primary_teacher_name'];
        }
        return $this->subject_teacher?->teacher?->full_name ?? '';
    }

    public function getAlternateTeacherNameAttribute() {
        $meta = $this->meta;
        if (!empty($meta['alt_teacher_name'])) {
            return $meta['alt_teacher_name'];
        }
        if (!empty($meta['alt_teacher_id'])) {
            $user = User::find($meta['alt_teacher_id']);
            return $user ? $user->full_name : '';
        }
        return '';
    }

    public function getPeriodNoAttribute() {
        if ($this->period_id && $this->relationLoaded('period') && $this->period) {
            return $this->period->period_number;
        }
        $meta = $this->meta;
        return $meta['period_no'] ?? null;
    }

    public function getTitleAttribute() {
        if ($this->type === "Lecture") {
            $subjectName = $this->subject->name ?? 'Lecture';
            $subjectType = $this->subject->type ?? '';
            $typeStr = $subjectType ? " ($subjectType)" : "";
            $teacher = $this->primary_teacher_name;
            $alt = $this->alternate_teacher_name;

            $title = $subjectName . $typeStr;
            if ($teacher) {
                $title .= ' - ' . $teacher;
            }
            if ($alt) {
                $title .= ' [Alt: ' . $alt . ']';
            }
            return $title;
        }

        if ($this->type === "Break") {
            $meta = $this->meta;
            if (!empty($meta['note'])) {
                return $meta['note'];
            }
            if (!empty($this->note) && !str_starts_with(trim($this->note), '{')) {
                return $this->note;
            }
            return trans("Break");
        }

        if (!empty($this->note) && !str_starts_with(trim($this->note), '{')) {
            return $this->note;
        }

        return trans("Lecture");
    }

    public function scopeCurrentSemesterData($query){
        $currentSemester = app(SemesterInterface::class)->default();
        if($currentSemester){
            $query->where(function ($query) use($currentSemester){
                $query->where('timetables.semester_id', $currentSemester->id)->orWhereNull('timetables.semester_id');
            });
        }
    }
}
