<?php

namespace App\Repositories\Saas;

use App\Repositories\Base\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/*
 * This Repository is the base for all the SaaS Features
 * It includes Below Features
 * 1. By Default Apply Owner Scope the model
 * 2. Add School ID automatically while Creating an Entry
 */

class SaaSRepository extends BaseRepository
{


    public function defaultModel()
    {
        return parent::defaultModel()->owner();
    }

    /**
     * A school user always writes into their own school: a school_id arriving in the
     * payload (often straight from $request->all()) is overridden, not trusted. Only a
     * user without a school (Super Admin) may target a school explicitly.
     */
    private static function scopeSchoolId(array $payload): array
    {
        $user = Auth::user();
        if ($user && $user->school_id) {
            $payload['school_id'] = $user->school_id;
        } elseif (empty($payload['school_id'])) {
            $payload['school_id'] = $user?->school_id;
        }
        return $payload;
    }

    public function create(array $payload): Model
    {
        $payload = self::scopeSchoolId($payload);
        return parent::create($payload)->fresh();
    }

    public function createBulk(array $payload): bool
    {
        $payload = array_map(static function ($d) {
            $user = Auth::user();
            
            if ($user && $user->school_id) {
                $d['school_id'] = $user->school_id;
            } elseif (!empty($d['school_id'])) {
                $d['school_id'] = $d['school_id'];
            } 
            return $d;
        }, $payload);
        return parent::createBulk($payload);
    }

    public function update(int $modelId, array $payload): ?Model
    {
        $payload = self::scopeSchoolId($payload);
        return parent::update($modelId, $payload);

    }

    public function updateOrCreate(array $uniqueColumns, array $updatingColumn): Model
    {
        $uniqueColumns = self::scopeSchoolId($uniqueColumns);
        return parent::updateOrCreate($uniqueColumns, $updatingColumn);
    }

    public function upsert(array $payload, array $uniqueColumns, array $updatingColumn): bool
    {
        $payload = array_map(static function ($d) {
            $d['school_id'] = Auth::user()->school_id;
            return $d;
        }, $payload);
        $uniqueColumns[] = ['school_id'];
        return parent::upsert($payload, $uniqueColumns, $updatingColumn);
    }
}
