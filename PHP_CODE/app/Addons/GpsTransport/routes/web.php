<?php

use App\Addons\GpsTransport\Http\Controllers\Web\GpsAlertController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsDashboardController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsEmergencyController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsHistoryController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsLiveTrackingController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsRouteController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsSettingController;
use App\Addons\GpsTransport\Http\Controllers\Web\GpsTripController;
use Illuminate\Support\Facades\Route;

/*
 * Web Routes for GPS Transport Tracking Addon Module.
 * Scoped under standard admin middleware with tenant database switching (SwitchDatabase).
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
])->prefix('gps')->group(static function () {

    // 1. Dashboard
    Route::get('/', [GpsDashboardController::class, 'index'])->name('gps.dashboard');
    Route::get('dashboard/live-stats', [GpsDashboardController::class, 'liveStats'])->name('gps.dashboard.live-stats');

    // 2. Live Fleet Map & Tracking
    Route::get('live-tracking', [GpsLiveTrackingController::class, 'index'])->name('gps.live-tracking');

    // 3. Route Builder & Geofences
    Route::get('routes', [GpsRouteController::class, 'index'])->name('gps.routes.index');
    Route::get('routes/{id}/data', [GpsRouteController::class, 'getRouteData'])->name('gps.routes.data');
    Route::post('routes/save-geometry', [GpsRouteController::class, 'saveGeometry'])->name('gps.routes.save-geometry');
    Route::post('routes/save', [GpsRouteController::class, 'saveRoute'])->name('gps.routes.save');
    Route::post('routes/{id}/assign-vehicle', [GpsRouteController::class, 'assignVehicle'])->name('gps.routes.assign-vehicle');
    Route::delete('routes/{id}/vehicles/{assignmentId}', [GpsRouteController::class, 'unassignVehicle'])->name('gps.routes.unassign-vehicle');

    // 4. Trip Management
    Route::get('trips', [GpsTripController::class, 'index'])->name('gps.trips.index');
    Route::post('trips/store', [GpsTripController::class, 'store'])->name('gps.trips.store');
    Route::post('trips/{id}/start', [GpsTripController::class, 'start'])->name('gps.trips.start');
    Route::post('trips/{id}/complete', [GpsTripController::class, 'complete'])->name('gps.trips.complete');

    // 5. Trip History & Replay
    Route::get('history', [GpsHistoryController::class, 'index'])->name('gps.history.index');
    Route::get('history/replay/{id}', [GpsHistoryController::class, 'replayData'])->name('gps.history.replay');

    // 6. SOS & Breakdown Emergency Center
    Route::get('emergency', [GpsEmergencyController::class, 'index'])->name('gps.emergency.index');
    Route::post('emergency/{id}/assign-replacement', [GpsEmergencyController::class, 'assignReplacement'])->name('gps.emergency.assign-replacement');
    Route::post('emergency/{id}/resolve', [GpsEmergencyController::class, 'resolve'])->name('gps.emergency.resolve');

    // 7. Safety Alerts
    Route::get('alerts', [GpsAlertController::class, 'index'])->name('gps.alerts.index');
    Route::post('alerts/{id}/resolve', [GpsAlertController::class, 'resolve'])->name('gps.alerts.resolve');
    Route::get('alerts/live', [GpsAlertController::class, 'live'])->name('gps.alerts.live');
    Route::post('alerts/{id}/acknowledge', [GpsAlertController::class, 'acknowledge'])->name('gps.alerts.acknowledge');

    // 8. GPS Module Settings
    Route::get('settings', [GpsSettingController::class, 'index'])->name('gps.settings.index');
    Route::post('settings/update', [GpsSettingController::class, 'update'])->name('gps.settings.update');
});
