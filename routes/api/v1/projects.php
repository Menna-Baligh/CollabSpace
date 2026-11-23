<?php

use App\Http\Controllers\Api\V1\Projects\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::match(['put', 'patch'], '/{project}', [ProjectController::class, 'update'])->name('update');
    Route::patch('/{project}/archive', [ProjectController::class, 'archive'])->name('archive');
    Route::patch('/{project}/unarchive', [ProjectController::class, 'unarchive'])->name('unarchive');
    Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
});
