<?php

use App\Http\Controllers\Api\V1\Files\ProjectFileController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects/{project}/files')->name('projects.files.')->group(function () {
    Route::get('/', [ProjectFileController::class, 'index'])->name('index');
    Route::post('/', [ProjectFileController::class, 'store'])->name('store');
    Route::get('/{file}/download', [ProjectFileController::class, 'download'])->name('download');
    Route::delete('/{file}', [ProjectFileController::class, 'destroy'])->name('destroy');
});
