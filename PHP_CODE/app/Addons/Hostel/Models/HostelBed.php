<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class HostelBed extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_beds';
    protected $fillable = ['school_id', 'room_id', 'bed_number', 'current_allocation_id'];

    public function room()
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }

    public function currentAllocation()
    {
        return $this->belongsTo(HostelAllocation::class, 'current_allocation_id');
    }
}
