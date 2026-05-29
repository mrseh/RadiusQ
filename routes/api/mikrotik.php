<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mikrotik\MikrotikController;

Route::prefix('mikrotik')->group(function () {
    Route::get('/ajax', [MikrotikController::class, 'ajax']);
    Route::post('/store', [MikrotikController::class, 'store']);
    Route::put('/update/{id}', [MikrotikController::class, 'update']);
    Route::get('/show/{id}', [MikrotikController::class, 'show']);
    Route::delete('/destroy/{id}', [MikrotikController::class, 'destroy']);
    Route::post('/probe/{id}', [MikrotikController::class, 'probe']);
    Route::post('/{id}/script', [MikrotikController::class, 'script']);
});
