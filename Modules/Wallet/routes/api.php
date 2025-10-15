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
    Route::prefix('wallets')->group(function () {
        Route::get('/', [WalletController::class, 'Wallets'])->name('wallet.all');
        Route::get('/{accountNumber}/balance', [WalletController::class, 'getWalletbalance'])->name('wallet.balance');
        Route::middleware(['kyc'])->group(function () {
            Route::post('/', [WalletController::class, 'createWallets'])->name('wallet.create');
            Route::post('/ngn', [WalletController::class, 'createNairaWallet'])->name('wallet.create-ngn');
            Route::post('/usd', [WalletController::class, 'createUsWallet'])->name('wallet.create-usd');
            Route::post('/usdc', [WalletController::class, 'createUsdcWallet'])->name('wallet.create-usdc');
            Route::post('/transfer/usd-usd', [WalletController::class, 'usdToUsd'])->name('wallet.usd-usd');
            
        });
    });
});


Route::get('/wallet-cronjob/usd', [WalletController::class, 'createUsWallet'])->name('wallet.usWalletCronjob');
Route::post('/bridge/webhook', [BridgeWebhookController::class, 'bridgeWebhook'])->name('wallet.bridgewebhook');

