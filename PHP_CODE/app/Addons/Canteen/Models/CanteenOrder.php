<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CanteenOrder extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_orders';
    protected $fillable = ['school_id', 'order_number', 'token_number', 'user_id', 'wallet_id', 'total_amount', 'payment_mode', 'order_type', 'status', 'service_date', 'served_by', 'notes'];
    protected $casts = ['total_amount' => 'decimal:2', 'service_date' => 'date'];

    public const STATUSES = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

    public function items()
    {
        return $this->hasMany(CanteenOrderItem::class, 'order_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
    public function wallet()
    {
        return $this->belongsTo(CanteenWallet::class, 'wallet_id');
    }
}
