<?php

use Illuminate\Support\Facades\Route;
use Modules\Walkthrough\Http\Controllers\WalkthroughController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('walkthroughs', WalkthroughController::class)->names('walkthrough');
});
