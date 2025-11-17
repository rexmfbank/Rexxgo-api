<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\app\Http\Controllers\NotificationController;

Route::middleware(['auth:borrower', 'throttle:10,1'])->group(function () {
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.all');
        Route::post('/send-push', [NotificationController::class, 'SendPushNotificationTouser'])->name('notifications.SendPushNotificationTouser');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    });
});