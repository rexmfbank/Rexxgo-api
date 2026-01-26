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
        Route::post('/update', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/kyc', [ProfileController::class, 'startKyc'])->name('profile.update-us');
        Route::post('/kyc-update', [ProfileController::class, 'kycUpdate'])->name('profile.kyc-update');
        
        Route::post('/fcm-token', [ProfileController::class, 'updateFcmToken'])->name('profile.updateFcmToken');
        Route::post('/enable-2fa', [ProfileController::class, 'enableTwoFa'])->name('profile.enable2fa');
        Route::post('/verify-enable-2fa', [ProfileController::class, 'verifyEnableTwoFa'])->name('profile.verifyEnableTwoFa');
        
        
        Route::get('/login-activities', [ProfileController::class, 'getLoginActivities'])->name('profile.login-activities');
        Route::get('/kyc-status', [ProfileController::class, 'getKycStatus'])->name('profile.kyc-status');
        Route::post('/passcode/reset', [ProfileController::class, 'resetPasscode'])->name('profile.resetPasscode');
        Route::post('/pin/reset', [ProfileController::class, 'resetPin'])->name('profile.resetPin');
        Route::post('/pin/verify', [ProfileController::class, 'ValidatePin'])->name('profile.ValidatePin');
    });
});
Route::get('/profile/occupation/list', [ProfileController::class, 'occupationList'])->name('profile.occupationList');
