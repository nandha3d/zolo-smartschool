<?php

namespace App\Addons\Library\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class LibraryDigitalAccessLog extends Model
{
    use BelongsToSchool;

    public $timestamps = false;
    protected $table = 'library_digital_access_logs';
    protected $fillable = ['school_id', 'book_id', 'user_id', 'accessed_at', 'ip_address', 'device'];
    protected $casts = ['accessed_at' => 'datetime'];
}
