<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LibraryIssue extends Model
{
    use BelongsToSchool;

    public const ISSUED = 'Issued';
    public const OVERDUE = 'Overdue';
    public const RETURNED = 'Returned';
    public const LOST = 'Lost';
    /** Loans still out with the member. */
    public const OPEN = [self::ISSUED, self::OVERDUE];

    protected $table = 'library_issues';
    protected $fillable = [
        'school_id', 'book_copy_id', 'member_id', 'issued_by', 'issue_date', 'due_date', 'return_date',
        'returned_to', 'status', 'renewal_count', 'late_fine_amount', 'remarks',
    ];
    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'late_fine_amount' => 'decimal:2',
    ];

    public function copy()
    {
        return $this->belongsTo(LibraryBookCopy::class, 'book_copy_id');
    }

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'member_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function fines()
    {
        return $this->hasMany(LibraryFine::class, 'issue_id');
    }
}
