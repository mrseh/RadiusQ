<?php

use App\Http\Controllers\Api\Invoice\PaidController;
use App\Http\Controllers\Api\Invoice\UnpaidController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('invoice')->group(function () {
    // Unpaid invoices
    Route::get('/unpaid/ajax', [UnpaidController::class, 'ajax']);
    Route::post('/unpaid/manual', [UnpaidController::class, 'manual']);
    Route::post('/unpaid/pay-selected', [UnpaidController::class, 'paySelected']);
    Route::post('/unpaid/send-selected', [UnpaidController::class, 'sendSelected']);
    Route::post('/unpaid/send-manual', [UnpaidController::class, 'sendManual']);
    Route::get('/unpaid/export', [UnpaidController::class, 'export']);
    Route::get('/unpaid/print', [UnpaidController::class, 'print']);
    Route::get('/unpaid/detail/{id}', [UnpaidController::class, 'detail']);
    Route::get('/unpaid/pelanggan/search', [UnpaidController::class, 'pelangganSearch']);
    Route::get('/unpaid/pelanggan/{id}', [UnpaidController::class, 'pelangganDetail']);
    Route::post('/unpaid/{id}/pay', [UnpaidController::class, 'pay']);
    Route::delete('/unpaid/delete-selected', [UnpaidController::class, 'deleteSelected']);
    Route::post('/unpaid/{id}/save', [UnpaidController::class, 'save']);
    Route::post('/unpaid/merge-duplicates', [UnpaidController::class, 'mergeDuplicates']);

    // Paid invoices
    Route::get('/paid/ajax', [PaidController::class, 'ajax']);
    Route::post('/paid/cancel-selected', [PaidController::class, 'cancelSelected']);
    Route::post('/paid/send-selected', [PaidController::class, 'sendSelected']);
    Route::delete('/paid/delete-selected', [PaidController::class, 'deleteSelected']);
    Route::get('/paid/export', [PaidController::class, 'export']);
    Route::get('/paid/print', [PaidController::class, 'print']);
    Route::get('/paid/detail/{id}', [PaidController::class, 'detail']);
});