<?php

use App\Addons\Canteen\Http\Controllers\CanteenMenuController;
use App\Addons\Canteen\Http\Controllers\CanteenPosController;
use App\Addons\Canteen\Http\Controllers\CanteenWalletController;
use Illuminate\Support\Facades\Route;

// Full admin-panel middleware stack (see the Photo Booth routes for why).
Route::middleware([
    'web', 'auth', 'Role', 'checkSchoolStatus', 'status', 'SwitchDatabase', 'verifiedEmail',
    'CheckForMaintenanceMode', '2fa', 'mustChangePassword', 'wizardSettings', 'language',
])->prefix('canteen')->name('canteen.')->group(static function () {
    Route::get('/', [CanteenMenuController::class, 'dashboard'])->name('dashboard');

    Route::get('menu', [CanteenMenuController::class, 'index'])->name('menu');
    Route::get('items/list', [CanteenMenuController::class, 'list'])->name('items.list');
    Route::post('items', [CanteenMenuController::class, 'store'])->name('items.store');
    Route::put('items/{id}', [CanteenMenuController::class, 'update'])->name('items.update');
    Route::delete('items/{id}', [CanteenMenuController::class, 'destroy'])->name('items.destroy');
    Route::post('items/{id}/toggle', [CanteenMenuController::class, 'toggle'])->name('items.toggle');
    Route::post('categories', [CanteenMenuController::class, 'storeCategory'])->name('categories.store');
    Route::delete('categories/{id}', [CanteenMenuController::class, 'destroyCategory'])->name('categories.destroy');

    Route::get('pos', [CanteenPosController::class, 'pos'])->name('pos');
    Route::get('pos/customer', [CanteenPosController::class, 'customer'])->name('pos.customer');
    Route::post('pos/checkout', [CanteenPosController::class, 'checkout'])->name('pos.checkout');
    Route::get('kitchen', [CanteenPosController::class, 'kitchen'])->name('kitchen');
    Route::get('kitchen/orders', [CanteenPosController::class, 'kitchenOrders'])->name('kitchen.orders');
    Route::post('orders/{id}/status', [CanteenPosController::class, 'setStatus'])->name('orders.status');
    Route::get('orders', [CanteenPosController::class, 'orders'])->name('orders');
    Route::get('orders/list', [CanteenPosController::class, 'ordersList'])->name('orders.list');

    Route::get('wallets', [CanteenWalletController::class, 'index'])->name('wallets');
    Route::get('wallets/list', [CanteenWalletController::class, 'list'])->name('wallets.list');
    Route::get('wallets/lookup', [CanteenWalletController::class, 'lookup'])->name('wallets.lookup');
    Route::get('wallets/transactions', [CanteenWalletController::class, 'transactions'])->name('wallets.transactions');
    Route::post('wallets/{id}/top-up', [CanteenWalletController::class, 'topUp'])->name('wallets.topup');
    Route::post('wallets/{id}/adjust', [CanteenWalletController::class, 'adjust'])->name('wallets.adjust');
    Route::put('wallets/{id}', [CanteenWalletController::class, 'updateControls'])->name('wallets.update');

    Route::get('inventory', [CanteenWalletController::class, 'inventory'])->name('inventory');
    Route::post('inventory', [CanteenWalletController::class, 'storeInventory'])->name('inventory.store');
    Route::post('inventory/{id}/move', [CanteenWalletController::class, 'moveInventory'])->name('inventory.move');
    Route::delete('inventory/{id}', [CanteenWalletController::class, 'destroyInventory'])->name('inventory.destroy');

    Route::get('meal-plans', [CanteenWalletController::class, 'plans'])->name('plans');
    Route::post('meal-plans', [CanteenWalletController::class, 'storePlan'])->name('plans.store');
    Route::post('meal-plans/{id}/toggle', [CanteenWalletController::class, 'togglePlan'])->name('plans.toggle');
    Route::post('meal-plans/subscribe', [CanteenWalletController::class, 'subscribe'])->name('plans.subscribe');
    Route::post('meal-plans/subscriptions/{id}/cancel', [CanteenWalletController::class, 'cancelSubscription'])->name('plans.subscriptions.cancel');
});
