<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Map\MapController;

Route::middleware('auth:sanctum')->prefix('map')->group(function () {

    Route::get('/user/options', [MapController::class, 'userOptions'])->name('map.user.options');
    Route::get('/user/data', [MapController::class, 'userData'])->name('map.user.data');

    Route::get('/odp/options', [MapController::class, 'odpOptions'])->name('map.odp.options');
    Route::get('/odp/data', [MapController::class, 'odpData'])->name('map.odp.data');
});