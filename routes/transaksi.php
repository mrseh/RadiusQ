<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Transaksi\TransaksiController;

Route::middleware('auth:sanctum')->prefix('transaksi')->group(function () {
    Route::get('/', [TransaksiController::class, 'index'])->name('transaksi');
    Route::get('/ajax', [TransaksiController::class, 'ajax'])->name('transaksi.ajax');
    Route::post('/store', [TransaksiController::class, 'store'])->name('transaksi.store');
    Route::get('/export/csv', [TransaksiController::class, 'exportCsv'])->name('transaksi.export.csv');
    Route::get('/report/daily', [TransaksiController::class, 'dailyReport'])->name('transaksi.report.daily');
    Route::get('/report/monthly', [TransaksiController::class, 'monthlyReport'])->name('transaksi.report.monthly');
    Route::delete('/empty', [TransaksiController::class, 'empty'])->name('transaksi.empty');
    Route::get('/stats', [TransaksiController::class, 'stats'])->name('transaksi.stats');
});