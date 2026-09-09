<?php

use App\Addons\PhotoBooth\Http\Controllers\PhotoBoothController;
use Illuminate\Support\Facades\Route;

/*
 * Loaded by PhotoBoothServiceProvider, so it does not inherit RouteServiceProvider's
 * groups — the stack has to be stated in full here.
 *
 * It mirrors the stack the rest of the admin panel runs under. SwitchDatabase is the
 * one that matters most: without it these routes would query the master database
 * instead of the school's own, so a lookup would silently cross tenants. 'auth' is
 * listed explicitly even though 'Role' already turns anonymous requests away.
 */
Route::middleware([
    'web',
    'auth',
    'Role',
    'checkSchoolStatus',
    'status',
    'SwitchDatabase',
    'verifiedEmail',
    'CheckForMaintenanceMode',
    '2fa',
    'mustChangePassword',
    'wizardSettings',
    'language',
])->prefix('photo-booth')->group(static function () {
    Route::get('/', [PhotoBoothController::class, 'index'])->name('photo-booth.index');
    Route::get('roster', [PhotoBoothController::class, 'roster'])->name('photo-booth.roster');
    Route::post('capture', [PhotoBoothController::class, 'store'])->name('photo-booth.capture');
});
