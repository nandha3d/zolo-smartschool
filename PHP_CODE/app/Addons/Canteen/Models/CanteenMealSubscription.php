<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CanteenMealSubscription extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_meal_subscriptions';
    protected $fillable = ['school_id', 'plan_id', 'user_id', 'start_date', 'end_date', 'amount', 'payment_mode', 'status', 'created_by'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'amount' => 'decimal:2'];

    public function plan()
    {
        return $this->belongsTo(CanteenMealPlan::class, 'plan_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
