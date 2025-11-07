<?php

use Illuminate\Support\Facades\Route;
use Modules\Treasury\app\Http\Controllers\TreasuryController;

Route::middleware(['auth:borrower', 'throttle:10,1'])->group(function () {
    Route::prefix('treasury')->group(function () {
        Route::get('/', [TreasuryController::class, 'Treasury'])->name('Treasury.all');
    });
});
