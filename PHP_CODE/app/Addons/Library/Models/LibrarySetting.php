<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibrarySetting extends Model
{
    use BelongsToSchool;

    protected $table = 'library_settings';
    protected $fillable = [
        'school_id', 'student_max_books', 'student_issue_days', 'staff_max_books', 'staff_issue_days',
        'fine_per_day', 'grace_period_days', 'max_renewals', 'skip_holidays', 'allow_online_reservation',
        'reservation_hold_days',
    ];
    protected $casts = [
        'fine_per_day' => 'decimal:2',
        'skip_holidays' => 'boolean',
        'allow_online_reservation' => 'boolean',
    ];
}
