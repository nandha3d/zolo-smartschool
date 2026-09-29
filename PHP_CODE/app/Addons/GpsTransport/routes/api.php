<?php

use App\Addons\GpsTransport\Http\Controllers\Api\DriverGpsApiController;
use App\Addons\GpsTransport\Http\Controllers\Api\ParentGpsApiController;
use App\Addons\GpsTransport\Http\Controllers\Api\SchoolGpsApiController;
use Illuminate\Support\Facades\Route;

/*
 * REST API Routes for GPS Transport Tracking Addon Module.
 * Dedicated endpoints for Driver Mobile App, Parent Mobile App, and School Portal.
 */

// 1. DRIVER MOBILE APP ENDPOINTS
Route::group([
    'prefix'     => 'api/driver/gps',
    'middleware' => ['APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::post('today-trips', [DriverGpsApiController::class, 'todayTrips']);
    Route::post('start-trip', [DriverGpsApiController::class, 'startTrip']);
    Route::post('location', [DriverGpsApiController::class, 'sendLocation']);
    Route::post('student-attendance', [DriverGpsApiController::class, 'recordAttendance']);
    Route::post('sos', [DriverGpsApiController::class, 'triggerSos']);
    Route::post('breakdown', [DriverGpsApiController::class, 'reportBreakdown']);
    Route::post('end-trip', [DriverGpsApiController::class, 'endTrip']);
});

// 2. PARENT MOBILE APP ENDPOINTS (Strictly Scoped)
Route::group([
    'prefix'     => 'api/parent/gps',
    'middleware' => ['APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::post('children-transport', [ParentGpsApiController::class, 'childrenTransport']);
    Route::post('track-bus', [ParentGpsApiController::class, 'trackBus']);
    Route::post('history', [ParentGpsApiController::class, 'history']);
});

// 3. SCHOOL LIVE FLEET ENDPOINTS
Route::group([
    'prefix'     => 'api/school/gps',
    'middleware' => ['APISwitchDatabase', 'auth:sanctum', 'checkSchoolStatus'],
], static function () {
    Route::get('fleet-live', [SchoolGpsApiController::class, 'fleetLive']);
    Route::get('trip/{id}/telemetry', [SchoolGpsApiController::class, 'tripTelemetry']);
});
