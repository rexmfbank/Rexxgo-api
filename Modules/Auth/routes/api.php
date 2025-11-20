<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Controllers\AuthController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('auths', AuthController::class)->names('auth');
});

Route::prefix('auth')
    ->middleware(['throttle:10,1'])
    ->group(function () {
        Route::get('company', [AuthController::class, 'getCompany']);
        Route::get('countries', [AuthController::class, 'getCountries']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('biometrics', [AuthController::class, 'setBiometrics']);
        Route::post('sendotp', [AuthController::class, 'sendOtp']);
        Route::post('verifyotp', [AuthController::class, 'verifyOtp']);
        Route::post('setpasscode', [AuthController::class, 'setPasscode']);
        Route::post('setpin', [AuthController::class, 'setPin']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('check-email', [AuthController::class, 'checkEmail']);
        
        Route::post('verify-2fa', [AuthController::class, 'verifyTwoFa']);
        
        Route::get('login', [AuthController::class, 'loginView'])->name('login');
        Route::get('logout', [AuthController::class, 'logout']);
        Route::get('refresh', [AuthController::class, 'refresh']);
        Route::post('/get-email', [AuthController::class, 'getEmail']);
        Route::post('otp/reset/send', [AuthController::class, 'sendResetOtp']);
        Route::post('otp/reset/verify', [AuthController::class, 'otpResetVerify']);
        Route::post('pin/reset', [AuthController::class, 'resetPin']);
        Route::post('passcode/reset', [AuthController::class, 'resetPasscode']);
        Route::post('facialid', [AuthController::class, 'facialId']);
});
