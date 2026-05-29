<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Perusahaan\PerusahaanController;
use App\Http\Controllers\Api\Perusahaan\BankController;

Route::prefix('perusahaan')->group(function () {
    Route::get('/company/get', [PerusahaanController::class, 'get']);
    Route::post('/company/save', [PerusahaanController::class, 'save']);

    Route::get('/bank/list', [BankController::class, 'index']);
    Route::post('/bank', [BankController::class, 'store']);
    Route::put('/bank/{id}', [BankController::class, 'update']);
    Route::delete('/bank/{id}', [BankController::class, 'destroy']);
    Route::put('/bank/{id}/default', [BankController::class, 'setDefault']);
});
