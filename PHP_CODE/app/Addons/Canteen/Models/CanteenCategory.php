<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class CanteenCategory extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_categories';
    protected $fillable = ['school_id', 'name', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(CanteenItem::class, 'category_id');
    }
}
