<?php

use App\Addons\Canteen\Http\Controllers\Api\CanteenApiController;
use Illuminate\Support\Facades\Route;

// Student, staff and parent apps. Guardians pass child_id.
Route::group([
    'prefix' => 'api/canteen',
    'middleware' => ['api', 'APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::get('wallet', [CanteenApiController::class, 'wallet']);
    Route::post('wallet/controls', [CanteenApiController::class, 'controls']);
    Route::get('menu', [CanteenApiController::class, 'menu']);
    Route::post('pre-order', [CanteenApiController::class, 'preOrder']);
});
