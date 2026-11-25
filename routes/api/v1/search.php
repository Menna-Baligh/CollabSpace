<?php

use App\Http\Controllers\Api\V1\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::get('search', [GlobalSearchController::class, 'index'])->name('search.index');
