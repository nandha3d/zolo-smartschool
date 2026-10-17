<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class HostelAttendance extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_attendances';
    protected $fillable = ['school_id', 'block_id', 'student_id', 'date', 'roll_call', 'status', 'marked_by', 'remarks'];
    protected $casts = ['date' => 'date'];

    public const STATUSES = ['Present', 'Absent', 'On_Leave', 'Late'];
}
