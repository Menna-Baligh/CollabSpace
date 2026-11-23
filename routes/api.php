<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Files\ProjectFileController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use App\Http\Controllers\Api\V1\Projects\ProjectTeamController;
use App\Http\Controllers\Api\V1\Projects\TeamMemberController;
use App\Http\Controllers\Api\V1\Tasks\GlobalTaskController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use App\Http\Controllers\Api\V1\Users\UserSuspensionController;
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

        // Protected auth routes (Sanctum + Active account check)
        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('/logout', LogoutController::class)->middleware('throttle:60,1');
            Route::get('/me', ProfileController::class)->middleware('throttle:60,1');
        });
    });

    // Protected application routes
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        // Projects CRUD & Archive
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update']);
        Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive']);
        Route::patch('/projects/{project}/unarchive', [ProjectController::class, 'unarchive']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

        // Project Members
        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::patch('/projects/{project}/members/{user}', [ProjectMemberController::class, 'update']);
        Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

        // Project Teams
        Route::get('/projects/{project}/teams', [ProjectTeamController::class, 'index']);
        Route::post('/projects/{project}/teams', [ProjectTeamController::class, 'store']);
        Route::patch('/projects/{project}/teams/{team}', [ProjectTeamController::class, 'update']);
        Route::delete('/projects/{project}/teams/{team}', [ProjectTeamController::class, 'destroy']);

        // Team Members
        Route::post('/projects/{project}/teams/{team}/members', [TeamMemberController::class, 'store']);
        Route::delete('/projects/{project}/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy']);

        // Global Tasks
        Route::get('/tasks', [GlobalTaskController::class, 'index']);
        Route::post('/tasks', [GlobalTaskController::class, 'store']);

        // Project Tasks
        Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
        Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
        Route::get('/projects/{project}/tasks/{task}', [TaskController::class, 'show']);
        Route::patch('/projects/{project}/tasks/{task}', [TaskController::class, 'update']);
        Route::patch('/projects/{project}/tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::delete('/projects/{project}/tasks/{task}', [TaskController::class, 'destroy']);

        // Project Files
        Route::get('/projects/{project}/files', [ProjectFileController::class, 'index']);
        Route::post('/projects/{project}/files', [ProjectFileController::class, 'store']);
        Route::get('/projects/{project}/files/{file}/download', [ProjectFileController::class, 'download']);
        Route::delete('/projects/{project}/files/{file}', [ProjectFileController::class, 'destroy']);

        // User Suspension & PM Handover (System Administrator only)
        Route::post('/users/{user}/suspend', UserSuspensionController::class);
    });
});
