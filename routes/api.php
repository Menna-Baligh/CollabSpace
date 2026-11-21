<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\GoogleAccountLinkController;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // Public guest routes
        Route::post('/register', RegisterController::class)->middleware('throttle:10,1');
        Route::post('/login', LoginController::class)->middleware('throttle:5,1');
        Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');
        Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1');

        // Google OAuth login & exchange routes
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1');
        Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1');
        Route::post('/google/exchange', [GoogleAuthController::class, 'exchange'])->middleware('throttle:20,1');

        // Google Account Linking callback (state-authenticated)
        Route::get('/google/link/callback', [GoogleAccountLinkController::class, 'callback'])->middleware('throttle:20,1');

        // Protected routes (Sanctum + Active account check)
        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('/logout', LogoutController::class)->middleware('throttle:60,1');
            Route::get('/me', ProfileController::class)->middleware('throttle:60,1');
            Route::get('/google/link/redirect', [GoogleAccountLinkController::class, 'redirect'])->middleware('throttle:10,1');
        });
    });
});
