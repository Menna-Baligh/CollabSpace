<?php

use App\Http\Controllers\Api\V1\Tasks\GlobalTaskController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

// Global Tasks
Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [GlobalTaskController::class, 'index'])->name('index');
    Route::post('/', [GlobalTaskController::class, 'store'])->name('store');
});

// Project Tasks
Route::prefix('projects/{project}/tasks')->name('projects.tasks.')->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::get('/{task}', [TaskController::class, 'show'])->name('show');
    Route::patch('/{task}', [TaskController::class, 'update'])->name('update');
    Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->name('update-status');
    Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
});
