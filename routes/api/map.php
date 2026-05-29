<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Map\MapController;

Route::prefix('map')->group(function () {
    Route::get('/user/options', [MapController::class, 'userOptions']);
    Route::get('/user/data', [MapController::class, 'userData']);
    Route::get('/odp/options', [MapController::class, 'odpOptions']);
    Route::get('/odp/data', [MapController::class, 'odpData']);
});
