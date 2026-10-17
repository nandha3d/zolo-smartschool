<?php

use App\Addons\Library\Http\Controllers\LibraryCatalogController;
use App\Addons\Library\Http\Controllers\LibraryCirculationController;
use App\Addons\Library\Http\Controllers\LibraryLedgerController;
use Illuminate\Support\Facades\Route;

/*
 * Loaded by LibraryServiceProvider, so the admin panel's middleware stack is stated in
 * full (see the Photo Booth routes for why SwitchDatabase matters).
 */
Route::middleware([
    'web', 'auth', 'Role', 'checkSchoolStatus', 'status', 'SwitchDatabase', 'verifiedEmail',
    'CheckForMaintenanceMode', '2fa', 'mustChangePassword', 'wizardSettings', 'language',
])->prefix('library')->name('library.')->group(static function () {
    Route::get('/', [LibraryLedgerController::class, 'dashboard'])->name('dashboard');

    Route::get('catalog', [LibraryCatalogController::class, 'index'])->name('catalog');
    Route::get('books/list', [LibraryCatalogController::class, 'list'])->name('books.list');
    Route::post('books', [LibraryCatalogController::class, 'store'])->name('books.store');
    Route::put('books/{id}', [LibraryCatalogController::class, 'update'])->name('books.update');
    Route::delete('books/{id}', [LibraryCatalogController::class, 'destroy'])->name('books.destroy');
    Route::get('books/{id}/copies', [LibraryCatalogController::class, 'copies'])->name('books.copies');
    Route::post('books/{id}/copies', [LibraryCatalogController::class, 'addCopies'])->name('books.copies.store');
    Route::get('books/{id}/ebook', [LibraryCatalogController::class, 'ebook'])->name('books.ebook');
    Route::put('copies/{id}', [LibraryCatalogController::class, 'updateCopy'])->name('copies.update');
    Route::delete('copies/{id}', [LibraryCatalogController::class, 'destroyCopy'])->name('copies.destroy');
    Route::post('categories', [LibraryCatalogController::class, 'storeCategory'])->name('categories.store');
    Route::delete('categories/{id}', [LibraryCatalogController::class, 'destroyCategory'])->name('categories.destroy');

    Route::get('circulation', [LibraryCirculationController::class, 'index'])->name('circulation');
    Route::get('circulation/member', [LibraryCirculationController::class, 'member'])->name('circulation.member');
    Route::get('circulation/copy', [LibraryCirculationController::class, 'copy'])->name('circulation.copy');
    Route::get('circulation/books', [LibraryCirculationController::class, 'books'])->name('circulation.books');
    Route::post('circulation/issue', [LibraryCirculationController::class, 'issue'])->name('circulation.issue');
    Route::post('circulation/return', [LibraryCirculationController::class, 'return'])->name('circulation.return');
    Route::post('circulation/reserve', [LibraryCirculationController::class, 'reserve'])->name('circulation.reserve');
    Route::post('loans/{id}/renew', [LibraryCirculationController::class, 'renew'])->name('loans.renew');
    Route::post('loans/{id}/lost', [LibraryCirculationController::class, 'lost'])->name('loans.lost');

    Route::get('loans', [LibraryLedgerController::class, 'issues'])->name('loans');
    Route::get('loans/list', [LibraryLedgerController::class, 'issuesList'])->name('loans.list');
    Route::get('fines', [LibraryLedgerController::class, 'fines'])->name('fines');
    Route::get('fines/list', [LibraryLedgerController::class, 'finesList'])->name('fines.list');
    Route::post('fines/{id}/settle', [LibraryLedgerController::class, 'settleFine'])->name('fines.settle');
    Route::get('reservations', [LibraryLedgerController::class, 'reservations'])->name('reservations');
    Route::get('reservations/list', [LibraryLedgerController::class, 'reservationsList'])->name('reservations.list');
    Route::post('reservations/{id}/cancel', [LibraryLedgerController::class, 'cancelReservation'])->name('reservations.cancel');
    Route::put('members/{id}', [LibraryLedgerController::class, 'updateMember'])->name('members.update');
    Route::get('settings', [LibraryLedgerController::class, 'settings'])->name('settings');
    Route::put('settings', [LibraryLedgerController::class, 'updateSettings'])->name('settings.update');
});
