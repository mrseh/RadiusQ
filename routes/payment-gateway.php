<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Payment\PaymentGatewayController;

Route::middleware('auth:sanctum')->prefix('payment-gateway')->group(function () {

    Route::get('/ajax', [PaymentGatewayController::class, 'ajax'])->name('payment-gateway.ajax');

    Route::match(['get', 'post'], '/setting', [PaymentGatewayController::class, 'setting'])
        ->name('payment-gateway.setting');

    // Withdraw
    Route::post('/withdraw/ajax', [PaymentGatewayController::class, 'withdrawAjax'])
        ->name('payment-gateway.withdraw.ajax');
    Route::post('/withdraw/default-bank', [PaymentGatewayController::class, 'withdrawDefaultBank'])
        ->name('payment-gateway.withdraw.default-bank');
    Route::post('/withdraw/available', [PaymentGatewayController::class, 'withdrawAvailable'])
        ->name('payment-gateway.withdraw.available');
    Route::post('/withdraw/request-otp', [PaymentGatewayController::class, 'withdrawRequestOtp'])
        ->name('payment-gateway.withdraw.request-otp');
    Route::post('/withdraw/confirm', [PaymentGatewayController::class, 'withdrawConfirm'])
        ->name('payment-gateway.withdraw.confirm');
});