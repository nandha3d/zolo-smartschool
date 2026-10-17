<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CanteenWallet extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_wallets';
    protected $fillable = ['school_id', 'user_id', 'balance', 'rfid_card', 'daily_limit', 'blocked_category_ids', 'allergens', 'is_locked'];
    protected $casts = ['balance' => 'decimal:2', 'daily_limit' => 'decimal:2', 'blocked_category_ids' => 'array', 'allergens' => 'array', 'is_locked' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
    public function transactions()
    {
        return $this->hasMany(CanteenWalletTransaction::class, 'wallet_id');
    }
}
