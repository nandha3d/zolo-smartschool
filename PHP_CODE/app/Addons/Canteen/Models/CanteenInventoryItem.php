<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class CanteenInventoryItem extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_inventory_items';
    protected $fillable = ['school_id', 'name', 'unit', 'current_stock', 'reorder_level', 'unit_cost', 'supplier'];
    protected $casts = ['current_stock' => 'decimal:3', 'reorder_level' => 'decimal:3', 'unit_cost' => 'decimal:2'];

    public function movements()
    {
        return $this->hasMany(CanteenInventoryMovement::class, 'inventory_item_id');
    }
}
