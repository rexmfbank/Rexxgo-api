<?php

use Illuminate\Support\Facades\Route;
use Modules\Wallet\app\Http\Controllers\BridgeWebhookController;
use Modules\Wallet\app\Http\Controllers\WalletController;

/*
|--------------------------------------------------------------------------
| Wallet API Routes
|--------------------------------------------------------------------------
|
| These routes are protected by the borrower authentication guard.
| They provide access to wallet functionality for authenticated users.
|
*/

Route::middleware(['auth:borrower', 'throttle:10,1'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Wallet Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('wallet')->group(function () {
        Route::get('/{accountNumber}/balance', [WalletController::class, 'getBalance'])->name('wallet.balance');
        Route::post('/ngn', [WalletController::class, 'createNairaWallet'])->name('wallet.create-ngn');
    });

    /*
    |--------------------------------------------------------------------------
    | Alternative Routes (without prefix)
    |--------------------------------------------------------------------------
    */
    Route::get('/wallet/{accountNumber}/balance', [WalletController::class, 'getBalance'])->name('wallet.get-balance');
    Route::post('/wallet/ngn', [WalletController::class, 'createNairaWallet'])->name('wallet.create-ngn-alt');
});


Route::get('/wallet-cronjob/usd', [WalletController::class, 'createUsWallet'])->name('wallet.usWalletCronjob');
Route::post('/bridge/webhook', [BridgeWebhookController::class, 'bridgeWebhook'])->name('wallet.bridgewebhook');

