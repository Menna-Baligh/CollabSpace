<?php

use App\Http\Controllers\Api\V1\Projects\ProjectTeamController;
use App\Http\Controllers\Api\V1\Projects\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects/{project}/teams')->name('projects.teams.')->group(function () {
    Route::get('/', [ProjectTeamController::class, 'index'])->name('index');
    Route::post('/', [ProjectTeamController::class, 'store'])->name('store');
    Route::patch('/{team}', [ProjectTeamController::class, 'update'])->name('update');
    Route::delete('/{team}', [ProjectTeamController::class, 'destroy'])->name('destroy');

    Route::prefix('/{team}/members')->name('members.')->group(function () {
        Route::post('/', [TeamMemberController::class, 'store'])->name('store');
        Route::delete('/{user}', [TeamMemberController::class, 'destroy'])->name('destroy');
    });
});
