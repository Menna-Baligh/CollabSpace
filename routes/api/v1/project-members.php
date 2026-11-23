<?php

use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects/{project}/members')->name('projects.members.')->group(function () {
    Route::post('/', [ProjectMemberController::class, 'store'])->name('store');
    Route::patch('/{user}', [ProjectMemberController::class, 'update'])->name('update');
    Route::delete('/{user}', [ProjectMemberController::class, 'destroy'])->name('destroy');
});
