<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\Students;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HostelAllocation extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_allocations';
    protected $fillable = ['school_id', 'student_id', 'user_id', 'bed_id', 'session_year_id', 'allocation_date', 'vacate_date', 'status', 'fee_amount', 'remarks'];
    protected $casts = ['allocation_date' => 'date', 'vacate_date' => 'date', 'fee_amount' => 'decimal:2'];

    public const ACTIVE = 'Active';

    public function bed()
    {
        return $this->belongsTo(HostelBed::class, 'bed_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function fees()
    {
        return $this->hasMany(HostelFee::class, 'allocation_id');
    }
}
