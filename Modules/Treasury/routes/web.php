<?php

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('treasuries', TreasuryController::class)->names('treasury');
});
