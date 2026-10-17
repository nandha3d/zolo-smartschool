<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\Students;
use Illuminate\Database\Eloquent\Model;

class HostelVisitor extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_visitors';
    protected $fillable = ['school_id', 'student_id', 'visitor_name', 'relation', 'contact', 'id_proof', 'purpose', 'in_time', 'out_time', 'logged_by'];
    protected $casts = ['in_time' => 'datetime', 'out_time' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id')->withTrashed();
    }
}
