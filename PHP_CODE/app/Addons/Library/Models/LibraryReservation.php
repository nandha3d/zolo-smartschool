<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibraryReservation extends Model
{
    use BelongsToSchool;

    public const PENDING = 'Pending';
    /** A copy has come back and is held for this member until expiry_date. */
    public const READY = 'Ready';
    public const FULFILLED = 'Fulfilled';
    public const EXPIRED = 'Expired';
    public const CANCELLED = 'Cancelled';
    public const ACTIVE = [self::PENDING, self::READY];

    protected $table = 'library_reservations';
    protected $fillable = [
        'school_id', 'book_id', 'member_id', 'reservation_date', 'expiry_date', 'status', 'held_copy_id', 'notified_at',
    ];
    protected $casts = ['reservation_date' => 'date', 'expiry_date' => 'date', 'notified_at' => 'datetime'];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id')->withTrashed();
    }

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'member_id');
    }

    public function heldCopy()
    {
        return $this->belongsTo(LibraryBookCopy::class, 'held_copy_id');
    }
}
