<?php

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
    Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
});
