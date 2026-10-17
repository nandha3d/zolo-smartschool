<?php

use App\Addons\Hostel\Http\Controllers\HostelGateController;
use App\Addons\Hostel\Http\Controllers\HostelResidencyController;
use App\Addons\Hostel\Http\Controllers\HostelSetupController;
use Illuminate\Support\Facades\Route;

// Full admin-panel middleware stack (see the Photo Booth routes for why).
Route::middleware([
    'web', 'auth', 'Role', 'checkSchoolStatus', 'status', 'SwitchDatabase', 'verifiedEmail',
    'CheckForMaintenanceMode', '2fa', 'mustChangePassword', 'wizardSettings', 'language',
])->prefix('hostel')->name('hostel.')->group(static function () {
    Route::get('/', [HostelResidencyController::class, 'dashboard'])->name('dashboard');

    Route::get('setup', [HostelSetupController::class, 'index'])->name('setup');
    Route::get('rooms', [HostelSetupController::class, 'rooms'])->name('rooms');
    Route::post('blocks', [HostelSetupController::class, 'storeBlock'])->name('blocks.store');
    Route::put('blocks/{id}', [HostelSetupController::class, 'updateBlock'])->name('blocks.update');
    Route::delete('blocks/{id}', [HostelSetupController::class, 'destroyBlock'])->name('blocks.destroy');
    Route::post('room-types', [HostelSetupController::class, 'storeRoomType'])->name('room-types.store');
    Route::put('room-types/{id}', [HostelSetupController::class, 'updateRoomType'])->name('room-types.update');
    Route::delete('room-types/{id}', [HostelSetupController::class, 'destroyRoomType'])->name('room-types.destroy');
    Route::post('rooms', [HostelSetupController::class, 'storeRooms'])->name('rooms.store');
    Route::put('rooms/{id}', [HostelSetupController::class, 'updateRoom'])->name('rooms.update');
    Route::delete('rooms/{id}', [HostelSetupController::class, 'destroyRoom'])->name('rooms.destroy');

    Route::get('allocations', [HostelResidencyController::class, 'allocations'])->name('allocations');
    Route::get('allocations/list', [HostelResidencyController::class, 'allocationsList'])->name('allocations.list');
    Route::get('students', [HostelResidencyController::class, 'students'])->name('students');
    Route::post('allocations', [HostelResidencyController::class, 'allocate'])->name('allocations.store');
    Route::post('allocations/{id}/vacate', [HostelResidencyController::class, 'vacate'])->name('allocations.vacate');
    Route::post('allocations/{id}/transfer', [HostelResidencyController::class, 'transfer'])->name('allocations.transfer');

    Route::get('attendance', [HostelResidencyController::class, 'attendance'])->name('attendance');
    Route::get('attendance/roll', [HostelResidencyController::class, 'rollCall'])->name('attendance.roll');
    Route::post('attendance', [HostelResidencyController::class, 'saveRollCall'])->name('attendance.store');

    Route::get('gate-passes', [HostelGateController::class, 'passes'])->name('passes');
    Route::get('gate-passes/list', [HostelGateController::class, 'passesList'])->name('passes.list');
    Route::post('gate-passes', [HostelGateController::class, 'storePass'])->name('passes.store');
    Route::post('gate-passes/scan', [HostelGateController::class, 'scan'])->name('passes.scan');
    Route::post('gate-passes/{id}/decide', [HostelGateController::class, 'decide'])->name('passes.decide');
    Route::post('gate-passes/{id}/cancel', [HostelGateController::class, 'cancel'])->name('passes.cancel');

    Route::get('visitors', [HostelGateController::class, 'visitors'])->name('visitors');
    Route::get('visitors/list', [HostelGateController::class, 'visitorsList'])->name('visitors.list');
    Route::post('visitors', [HostelGateController::class, 'storeVisitor'])->name('visitors.store');
    Route::post('visitors/{id}/checkout', [HostelGateController::class, 'checkoutVisitor'])->name('visitors.checkout');

    Route::get('fees', [HostelGateController::class, 'fees'])->name('fees');
    Route::get('fees/list', [HostelGateController::class, 'feesList'])->name('fees.list');
    Route::post('fees/generate', [HostelGateController::class, 'generateFees'])->name('fees.generate');
    Route::post('fees/{id}/settle', [HostelGateController::class, 'settleFee'])->name('fees.settle');
});
