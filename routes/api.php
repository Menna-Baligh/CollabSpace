<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Auth routes (public + protected sub-group handled internally)
    require __DIR__.'/api/v1/auth.php';

    // Protected application routes
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        require __DIR__.'/api/v1/projects.php';
        require __DIR__.'/api/v1/project-members.php';
        require __DIR__.'/api/v1/project-teams.php';
        require __DIR__.'/api/v1/tasks.php';
        require __DIR__.'/api/v1/files.php';
        require __DIR__.'/api/v1/users.php';
    });
});
