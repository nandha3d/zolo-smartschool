<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\Students;
use Illuminate\Database\Eloquent\Model;

class HostelFee extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_fees';
    protected $fillable = ['school_id', 'allocation_id', 'student_id', 'period_label', 'period_start', 'period_end', 'amount', 'due_date', 'status', 'payment_mode', 'receipt_no', 'paid_at', 'collected_by'];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'paid_at' => 'datetime', 'amount' => 'decimal:2'];

    public function allocation()
    {
        return $this->belongsTo(HostelAllocation::class, 'allocation_id');
    }

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id')->withTrashed();
    }
}
