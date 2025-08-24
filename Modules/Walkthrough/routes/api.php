<?php

use Illuminate\Support\Facades\Route;
use Modules\Walkthrough\Http\Controllers\WalkthroughController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('walkthroughs', WalkthroughController::class)->names('walkthrough');
});
