<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Olt\OltController;

Route::prefix('olt')->group(function () {
    Route::get('/ajax', [OltController::class, 'ajax']);
    Route::post('/', [OltController::class, 'store']);
    Route::get('/', [OltController::class, 'show']);
    Route::get('/status', [OltController::class, 'oltStatus']);
    Route::get('/hioso', [OltController::class, 'hioso']);
    Route::get('/hsgq', [OltController::class, 'hsgq']);
    Route::get('/{id}', [OltController::class, 'oltDetail'])->name('olt.detail');
    Route::put('/{id}', [OltController::class, 'update']);
    Route::delete('/{id}', [OltController::class, 'destroy']);
});
