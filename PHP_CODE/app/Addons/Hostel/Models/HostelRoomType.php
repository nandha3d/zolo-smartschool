<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class HostelRoomType extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_room_types';
    protected $fillable = ['school_id', 'name', 'fee_cycle', 'base_fee', 'amenities'];
    protected $casts = ['amenities' => 'array', 'base_fee' => 'decimal:2'];

    public const CYCLES = ['Monthly' => 1, 'Quarterly' => 3, 'Annual' => 12];
}
