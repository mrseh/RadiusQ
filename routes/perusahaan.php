<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Perusahaan\PerusahaanController;

Route::middleware('auth:sanctum')->prefix('perusahaan')->group(function () {

    // Company
    Route::get('/company/get', [PerusahaanController::class, 'getCompany'])
        ->name('perusahaan.company.get');
    Route::post('/company/save', [PerusahaanController::class, 'saveCompany'])
        ->name('perusahaan.company.save');

    // Bank Accounts
    Route::get('/bank/list', [PerusahaanController::class, 'listBank'])
        ->name('perusahaan.bank.list');
    Route::post('/bank', [PerusahaanController::class, 'storeBank'])
        ->name('perusahaan.bank.store');
    Route::put('/bank/{id}', [PerusahaanController::class, 'updateBank'])
        ->name('perusahaan.bank.update');
    Route::delete('/bank/{id}', [PerusahaanController::class, 'deleteBank'])
        ->name('perusahaan.bank.destroy');
    Route::put('/bank/{id}/default', [PerusahaanController::class, 'setDefaultBank'])
        ->name('perusahaan.bank.set-default');
});