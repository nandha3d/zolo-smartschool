<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\Students;
use Illuminate\Database\Eloquent\Model;

class HostelGatePass extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_gate_passes';
    protected $fillable = ['school_id', 'student_id', 'pass_type', 'reason', 'out_time', 'expected_in_time', 'actual_out_time', 'actual_in_time', 'guardian_status', 'warden_status', 'status', 'code', 'requested_by', 'approved_by'];
    protected $casts = ['out_time' => 'datetime', 'expected_in_time' => 'datetime', 'actual_out_time' => 'datetime', 'actual_in_time' => 'datetime'];

    public const TYPES = ['Day_Out', 'Night_Out', 'Home_Visit'];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id')->withTrashed();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'Out' && $this->expected_in_time && $this->expected_in_time->isPast();
    }
}
