<?php

use App\Addons\Hostel\Http\Controllers\Api\HostelApiController;
use Illuminate\Support\Facades\Route;

// Student and parent apps. Guardians pass child_id.
Route::group([
    'prefix' => 'api/hostel',
    'middleware' => ['api', 'APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::get('overview', [HostelApiController::class, 'overview']);
    Route::post('gate-pass', [HostelApiController::class, 'requestPass']);
    Route::post('gate-pass/{id}/decision', [HostelApiController::class, 'decidePass']);
});
