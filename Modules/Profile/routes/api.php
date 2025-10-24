<?php

use Illuminate\Support\Facades\Route;
use Modules\Profile\app\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Profile API Routes
|--------------------------------------------------------------------------
|
| These routes are protected by the borrower authentication guard.
| They provide access to profile management functionality for authenticated users.
|
*/

Route::middleware(['auth:borrower', 'throttle:10,1'])->group(function () {
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('profile.index');
        Route::post('/kyc', [ProfileController::class, 'startKyc'])->name('profile.update-us');
        Route::get('/kyc-status', [ProfileController::class, 'getKycStatus'])->name('profile.kyc-status');
        Route::post('/passcode/reset', [ProfileController::class, 'resetPasscode'])->name('profile.resetPasscode');
        Route::post('/pin/reset', [ProfileController::class, 'resetPin'])->name('profile.resetPin');
        Route::post('/pin/verify', [ProfileController::class, 'ValidatePin'])->name('profile.ValidatePin');
    });
});
