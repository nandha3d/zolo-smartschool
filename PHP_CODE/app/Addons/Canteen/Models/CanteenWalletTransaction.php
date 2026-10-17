<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class CanteenWalletTransaction extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_wallet_transactions';
    protected $fillable = ['school_id', 'wallet_id', 'type', 'amount', 'balance_after', 'payment_mode', 'reference', 'order_id', 'created_by', 'note'];
    protected $casts = ['amount' => 'decimal:2', 'balance_after' => 'decimal:2'];

    public function wallet()
    {
        return $this->belongsTo(CanteenWallet::class, 'wallet_id');
    }
    public function order()
    {
        return $this->belongsTo(CanteenOrder::class, 'order_id');
    }
}
