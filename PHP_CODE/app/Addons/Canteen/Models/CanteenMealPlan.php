<?php

namespace App\Addons\Canteen\Models;

use App\Addons\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class CanteenMealPlan extends Model
{
    use BelongsToSchool;

    protected $table = 'canteen_meal_plans';
    protected $fillable = ['school_id', 'name', 'price', 'duration_days', 'description', 'is_active'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

}
