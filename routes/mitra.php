<?php

use App\Http\Controllers\Api\Mitra\BillerController;
use App\Http\Controllers\Api\Mitra\DepositController;
use App\Http\Controllers\Api\Mitra\OutletController;
use App\Http\Controllers\Api\Mitra\ResellerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('mitra')->group(function () {
    // Reseller
    Route::get('/reseller', [ResellerController::class, 'index']);
    Route::get('/reseller/ajax', [ResellerController::class, 'ajax']);
    Route::post('/reseller/store', [ResellerController::class, 'store']);

    // Biller
    Route::get('/biller', [BillerController::class, 'index']);
    Route::get('/biller/ajax', [BillerController::class, 'ajax']);
    Route::post('/biller/store', [BillerController::class, 'store']);

    // Outlet
    Route::get('/outlet', [OutletController::class, 'index']);
    Route::get('/outlet/ajax', [OutletController::class, 'ajax']);
    Route::post('/outlet/store', [OutletController::class, 'store']);

    // Deposit
    Route::get('/deposit', [DepositController::class, 'index']);
    Route::get('/deposit/ajax', [DepositController::class, 'ajax']);
    Route::post('/deposit/store', [DepositController::class, 'store']);
});
