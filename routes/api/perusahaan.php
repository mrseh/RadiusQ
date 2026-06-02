<?php

use App\Http\Controllers\Api\Perusahaan\PerusahaanController;
use Illuminate\Support\Facades\Route;

Route::prefix('perusahaan')->group(function () {
    Route::get('/company/get', [PerusahaanController::class, 'getCompany']);
    Route::post('/company/save', [PerusahaanController::class, 'saveCompany']);

    Route::get('/bank/list', [PerusahaanController::class, 'listBank']);
    Route::post('/bank', [PerusahaanController::class, 'storeBank']);
    Route::put('/bank/{id}', [PerusahaanController::class, 'updateBank']);
    Route::delete('/bank/{id}', [PerusahaanController::class, 'deleteBank']);
    Route::put('/bank/{id}/default', [PerusahaanController::class, 'setDefaultBank']);
});
