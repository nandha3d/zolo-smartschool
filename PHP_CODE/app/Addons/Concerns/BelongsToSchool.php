<?php

namespace App\Addons\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Addon tables live in each school's own database, so tenancy is already enforced by
 * the connection. This adds the usual school_id column handling on top: it is stamped
 * from the signed-in user on create, and queries made by a school user are limited to
 * their school as a second line of defence.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::creating(static function ($model) {
            if (empty($model->school_id) && Auth::check()) {
                $model->school_id = Auth::user()->school_id;
            }
        });

        static::addGlobalScope('school', static function (Builder $builder) {
            if (Auth::check() && Auth::user()->school_id) {
                $builder->where($builder->getModel()->getTable() . '.school_id', Auth::user()->school_id);
            }
        });
    }
}
