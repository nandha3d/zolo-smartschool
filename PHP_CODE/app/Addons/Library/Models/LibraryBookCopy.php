<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibraryBookCopy extends Model
{
    use BelongsToSchool;

    public const AVAILABLE = 'Available';
    public const ISSUED = 'Issued';
    public const RESERVED = 'Reserved';
    public const LOST = 'Lost';
    public const DAMAGED = 'Damaged';
    public const UNDER_REPAIR = 'Under_Repair';
    public const STATUSES = [self::AVAILABLE, self::ISSUED, self::RESERVED, self::LOST, self::DAMAGED, self::UNDER_REPAIR];
    public const CONDITIONS = ['New', 'Good', 'Fair', 'Poor'];
    public const ACQUISITION_TYPES = ['Purchase', 'Donation', 'Govt_Grant'];

    protected $table = 'library_book_copies';
    protected $fillable = [
        'school_id', 'book_id', 'barcode', 'accession_number', 'copy_number', 'status', 'condition',
        'acquisition_date', 'acquisition_type',
    ];
    protected $casts = ['acquisition_date' => 'date'];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id')->withTrashed();
    }

    public function issues()
    {
        return $this->hasMany(LibraryIssue::class, 'book_copy_id');
    }

    public function openIssue()
    {
        return $this->hasOne(LibraryIssue::class, 'book_copy_id')->whereIn('status', LibraryIssue::OPEN);
    }
}
