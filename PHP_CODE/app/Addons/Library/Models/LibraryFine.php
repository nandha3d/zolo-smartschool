<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibraryFine extends Model
{
    use BelongsToSchool;

    public const UNPAID = 'Unpaid';
    public const PAID = 'Paid';
    public const WAIVED = 'Waived';

    protected $table = 'library_fines';
    protected $fillable = [
        'school_id', 'issue_id', 'member_id', 'reason', 'amount', 'overdue_days', 'per_day_rate', 'status',
        'payment_mode', 'paid_at', 'waived_reason', 'collected_by',
    ];
    protected $casts = ['amount' => 'decimal:2', 'per_day_rate' => 'decimal:2', 'paid_at' => 'datetime'];

    public function issue()
    {
        return $this->belongsTo(LibraryIssue::class, 'issue_id');
    }

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'member_id');
    }
}
