<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Log\LogController;

Route::prefix('log')->group(function () {
    Route::get('/ajax', [LogController::class, 'ajax']);
    Route::delete('/clear-all', [LogController::class, 'clearAll']);
});
