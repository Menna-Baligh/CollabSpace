<?php

use App\Http\Controllers\Api\V1\Profile\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [UserProfileController::class, 'show'])->name('show');
    Route::get('/tasks', [UserProfileController::class, 'tasks'])->name('tasks');
    Route::get('/files', [UserProfileController::class, 'files'])->name('files');
    Route::get('/settings', [UserProfileController::class, 'showSettings'])->name('settings.show');
    Route::patch('/settings', [UserProfileController::class, 'updateSettings'])->name('settings.update');
    Route::post('/avatar', [UserProfileController::class, 'uploadAvatar'])->name('avatar.store');
});
