<?php

use App\Http\Controllers\Api\Payment\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

Route::prefix('payment-gateway')->group(function () {
    Route::get('/ajax', [PaymentGatewayController::class, 'ajax']);
    Route::match(['get', 'post'], '/setting', [PaymentGatewayController::class, 'setting']);

    Route::prefix('transaction')->group(function () {
        Route::get('/ajax', [PaymentGatewayController::class, 'transactionAjax']);
        Route::get('/balance', [PaymentGatewayController::class, 'transactionBalance']);
    });

    Route::prefix('withdraw')->group(function () {
        Route::get('/ajax', [PaymentGatewayController::class, 'withdrawAjax']);
        Route::post('/available', [PaymentGatewayController::class, 'withdrawAvailable']);
        Route::post('/request-otp', [PaymentGatewayController::class, 'withdrawRequestOtp']);
        Route::post('/confirm', [PaymentGatewayController::class, 'withdrawConfirm']);
        Route::put('/{id}/status', [PaymentGatewayController::class, 'withdrawUpdateStatus']);
    });

    Route::prefix('moota')->group(function () {
        Route::get('/config', [PaymentGatewayController::class, 'mootaConfig']);
        Route::put('/config', [PaymentGatewayController::class, 'mootaConfigSave']);
        Route::post('/sync', [PaymentGatewayController::class, 'mootaSync']);
        Route::get('/transactions', [PaymentGatewayController::class, 'mootaTransactions']);
    });
});
