<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Transaksi\TransaksiController;

Route::prefix('transaksi')->group(function () {
    Route::get('/ajax', [TransaksiController::class, 'ajax']);
    Route::post('/store', [TransaksiController::class, 'store']);
    Route::get('/', [TransaksiController::class, 'show']);
    Route::get('/export/csv', [TransaksiController::class, 'exportCsv']);
    Route::get('/report/daily', [TransaksiController::class, 'reportDaily']);
    Route::get('/report/monthly', [TransaksiController::class, 'reportMonthly']);
    Route::delete('/empty', [TransaksiController::class, 'empty']);
    Route::get('/stats', [TransaksiController::class, 'stats']);
});
