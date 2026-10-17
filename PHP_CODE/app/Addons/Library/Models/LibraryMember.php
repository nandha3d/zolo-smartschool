<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LibraryMember extends Model
{
    use BelongsToSchool;

    protected $table = 'library_members';
    protected $fillable = [
        'school_id', 'user_id', 'member_type', 'membership_number', 'max_books_allowed',
        'issue_duration_days', 'status', 'valid_until',
    ];
    protected $casts = ['valid_until' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function issues()
    {
        return $this->hasMany(LibraryIssue::class, 'member_id');
    }

    public function openIssues()
    {
        return $this->issues()->whereIn('status', LibraryIssue::OPEN);
    }

    public function fines()
    {
        return $this->hasMany(LibraryFine::class, 'member_id');
    }

    public function isStudent(): bool
    {
        return $this->member_type === 'Student';
    }
}
