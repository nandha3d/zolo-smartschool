<?php

namespace App\Addons\Canteen\Models;

use Illuminate\Database\Eloquent\Model;

class CanteenOrderItem extends Model
{
    public $timestamps = false;
    protected $table = 'canteen_order_items';
    protected $fillable = ['order_id', 'item_id', 'name', 'quantity', 'unit_price', 'subtotal'];
    protected $casts = ['unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];
}
