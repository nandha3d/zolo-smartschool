<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CanteenItem extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $table = 'canteen_items';
    protected $fillable = ['school_id', 'category_id', 'name', 'code', 'price', 'cost_price', 'image', 'dietary_type', 'allergens', 'calories', 'is_available', 'track_stock', 'stock_qty'];
    protected $casts = ['allergens' => 'array', 'is_available' => 'boolean', 'track_stock' => 'boolean', 'price' => 'decimal:2', 'cost_price' => 'decimal:2'];

    public const DIETARY = ['Veg', 'Non-Veg', 'Vegan', 'Egg'];
    public const ALLERGENS = ['peanuts', 'tree nuts', 'dairy', 'egg', 'gluten', 'soy', 'fish', 'shellfish', 'sesame'];

    public function category()
    {
        return $this->belongsTo(CanteenCategory::class, 'category_id');
    }
}
