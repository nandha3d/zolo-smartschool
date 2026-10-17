<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class HostelRoom extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_rooms';
    protected $fillable = ['school_id', 'block_id', 'room_type_id', 'room_number', 'floor', 'bed_capacity', 'status'];

    public function block()
    {
        return $this->belongsTo(HostelBlock::class, 'block_id');
    }

    public function roomType()
    {
        return $this->belongsTo(HostelRoomType::class, 'room_type_id');
    }

    public function beds()
    {
        return $this->hasMany(HostelBed::class, 'room_id');
    }
}
