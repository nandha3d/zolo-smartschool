<?php

use App\Addons\Library\Http\Controllers\Api\LibraryApiController;
use Illuminate\Support\Facades\Route;

// Student, staff and parent apps. Guardians pass child_id.
Route::group([
    'prefix' => 'api/library',
    'middleware' => ['api', 'APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::get('loans', [LibraryApiController::class, 'loans']);
    Route::get('catalog', [LibraryApiController::class, 'catalog']);
    Route::post('reserve', [LibraryApiController::class, 'reserve']);
    Route::get('ebook/{id}', [LibraryApiController::class, 'ebook']);
});
