<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class CanteenInventoryMovement extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_inventory_movements';
    protected $fillable = ['school_id', 'inventory_item_id', 'quantity_change', 'stock_after', 'reason', 'note', 'created_by'];
    protected $casts = ['quantity_change' => 'decimal:3', 'stock_after' => 'decimal:3'];

}
