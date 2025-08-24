<?php

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('treasuries', TreasuryController::class)->names('treasury');
});
