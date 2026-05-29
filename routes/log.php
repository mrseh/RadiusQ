<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Log\LogController;

Route::middleware('auth:sanctum')->prefix('log')->group(function () {

    Route::get('/ajax', [LogController::class, 'ajax'])->name('log.ajax');
    Route::delete('/clear-all', [LogController::class, 'clearAll'])->name('log.clear-all');
});