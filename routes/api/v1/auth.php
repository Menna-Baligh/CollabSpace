<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    // Public guest routes
    Route::post('/register', RegisterController::class)->middleware('throttle:10,1')->name('register');
    Route::post('/login', LoginController::class)->middleware('throttle:5,1')->name('login');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1')->name('forgot-password');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1')->name('reset-password');

    // Google OAuth login & exchange routes
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('google.redirect');
    Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('google.callback');
    Route::post('/google/exchange', [GoogleAuthController::class, 'exchange'])->middleware('throttle:20,1')->name('google.exchange');

    // Protected auth routes (Sanctum + Active account check)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::post('/logout', LogoutController::class)->middleware('throttle:60,1')->name('logout');
        Route::get('/me', ProfileController::class)->middleware('throttle:60,1')->name('me');
    });
});
