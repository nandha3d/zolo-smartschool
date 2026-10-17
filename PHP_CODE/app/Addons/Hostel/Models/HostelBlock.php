<?php

namespace App\Addons\Hostel\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HostelBlock extends Model
{
    use BelongsToSchool;

    protected $table = 'hostel_blocks';
    protected $fillable = ['school_id', 'name', 'code', 'type', 'total_floors', 'warden_id', 'notes'];

    public const TYPES = ['Boys', 'Girls', 'Coed', 'Staff'];

    public function rooms()
    {
        return $this->hasMany(HostelRoom::class, 'block_id');
    }

    public function warden()
    {
        return $this->belongsTo(User::class, 'warden_id')->withTrashed();
    }
}
