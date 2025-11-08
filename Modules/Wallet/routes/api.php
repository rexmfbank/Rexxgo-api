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

Route::middleware(['auth:borrower'])->group(function () {
    Route::prefix('wallets')->group(function () {
        Route::get('/', [WalletController::class, 'Wallets'])->name('wallet.all')->middleware('throttle:10,1');
        Route::get('/transactions', [WalletController::class, 'Transactions'])->name('wallet.transactions');
        Route::get('/banks', [WalletController::class, 'GetBanks'])->name('wallet.GetBanks');
        Route::post('/verify-account', [WalletController::class, 'VerifyAccountNumber'])->name('wallet.VerifyAccountNumber');
        Route::get('/{accountNumber}/balance', [WalletController::class, 'getWalletbalance'])->name('wallet.balance');
        Route::middleware(['kyc'])->group(function () {
            Route::post('/', [WalletController::class, 'createWallets'])->name('wallet.create');
            Route::post('/ngn', [WalletController::class, 'createNairaWallet'])->name('wallet.create-ngn');
            Route::post('/usd', [WalletController::class, 'createUsWallet'])->name('wallet.create-usd');
            Route::post('/usdc', [WalletController::class, 'createUsdcWallet'])->name('wallet.create-usdc');
            Route::post('/usdc/address', [WalletController::class, 'getLiquidationAddress'])->name('wallet.getLiquidationAddress');
            
            Route::post('/transfer', [WalletController::class, 'transfer'])->name('wallet.transfer');
            Route::post('/transfer/usd', [WalletController::class, 'transfertoUsBank'])->name('wallet.usd-usd');
            Route::post('/transfer/usdc', [WalletController::class, 'transferCrypto'])->name('wallet.usdc-usdc');
            Route::post('/transfer/ngn', [WalletController::class, 'TransferNgn'])->name('wallet.ngn-ngn');
            Route::post('/transfer/usdc/internal', [WalletController::class, 'transferCryptoInternal'])->name('wallet.usdc-usdc-internal');
            Route::post('/transfer/usd/internal', [WalletController::class, 'transferUsdInternal'])->name('wallet.usd-usd-internal');
            
            
            Route::get('/beneficiaries', [WalletController::class, 'getBeneficiariesByCurrency'])->name('wallet.getBeneficiariesByCurrency');
            
        });
    });
});


Route::get('/wallets/rates', [WalletController::class, 'Rates'])->name('wallet.Rates');
Route::get('/wallet-cronjob/usd', [WalletController::class, 'createUsWallet'])->name('wallet.usWalletCronjob');
Route::post('/bridge/webhook', [BridgeWebhookController::class, 'bridgeWebhook'])->name('wallet.bridgewebhook');
Route::get('/wallets-usds', [WalletController::class, 'getallusdwallets'])->name('wallet.getallusdwallets');
Route::get('/wallets-usdc', [WalletController::class, 'getAllBridgeWallets'])->name('wallet.getAllBridgeWallets');


