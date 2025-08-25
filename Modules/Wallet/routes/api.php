<?php

use Illuminate\Support\Facades\Route;
use Modules\Wallet\app\Http\Controllers\WalletController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('wallets', WalletController::class)->names('wallet');
});



Route::prefix('wallet')
    ->middleware(['auth:borrower','throttle:10,1'])
    ->group(function () {
        Route::get('create-ngn-wallet', [WalletController::class, 'createNairaWallet']);
        Route::get('getBalance/{accountno}', [WalletController::class, 'getBalance']);
    });
