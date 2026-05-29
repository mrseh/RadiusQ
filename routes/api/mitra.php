<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mitra\ResellerController;
use App\Http\Controllers\Api\Mitra\BillerController;
use App\Http\Controllers\Api\Mitra\OutletController;
use App\Http\Controllers\Api\Mitra\DepositController;

Route::prefix('mitra')->group(function () {
    Route::get('/reseller/ajax', [ResellerController::class, 'ajax']);
    Route::post('/reseller/store', [ResellerController::class, 'store']);
    Route::get('/reseller', [ResellerController::class, 'show']);

    Route::get('/biller/ajax', [BillerController::class, 'ajax']);
    Route::post('/biller/store', [BillerController::class, 'store']);
    Route::get('/biller', [BillerController::class, 'show']);

    Route::get('/outlet/ajax', [OutletController::class, 'ajax']);
    Route::post('/outlet/store', [OutletController::class, 'store']);
    Route::get('/outlet', [OutletController::class, 'show']);

    Route::get('/deposit/ajax', [DepositController::class, 'ajax']);
    Route::post('/deposit/store', [DepositController::class, 'store']);
    Route::get('/deposit', [DepositController::class, 'show']);
});
