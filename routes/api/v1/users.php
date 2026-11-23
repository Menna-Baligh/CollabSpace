<?php

use App\Http\Controllers\Api\V1\Users\UserSuspensionController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->name('users.')->group(function () {
    Route::post('/{user}/suspend', UserSuspensionController::class)->name('suspend');
});
