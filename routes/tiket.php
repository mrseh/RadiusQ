<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tiket\TiketGangguanController;

Route::middleware('auth:sanctum')->prefix('tiket/gangguan')->group(function () {
    Route::get('/ajax', [TiketGangguanController::class, 'ajax'])->name('tiket.gangguan.ajax');
    Route::get('/stats', [TiketGangguanController::class, 'stats'])->name('tiket.gangguan.stats');
    Route::post('/', [TiketGangguanController::class, 'store'])->name('tiket.gangguan.store');
    Route::get('/{id}/detail', [TiketGangguanController::class, 'detail'])->name('tiket.gangguan.detail');
    Route::get('/{id}/ambil', [TiketGangguanController::class, 'ambil'])->name('tiket.gangguan.ambil');
    Route::get('/{id}/tutup', [TiketGangguanController::class, 'tutup'])->name('tiket.gangguan.tutup');

    // Bulk actions
    Route::post('/bulk/open', [TiketGangguanController::class, 'bulkOpen'])->name('tiket.gangguan.bulk.open');
    Route::post('/bulk/progress', [TiketGangguanController::class, 'bulkProgress'])->name('tiket.gangguan.bulk.progress');
    Route::post('/bulk/close', [TiketGangguanController::class, 'bulkClose'])->name('tiket.gangguan.bulk.close');
    Route::post('/bulk/delete', [TiketGangguanController::class, 'bulkDelete'])->name('tiket.gangguan.bulk.delete');

    // Pelanggan
    Route::get('/pelanggan/search', [TiketGangguanController::class, 'searchPelanggan'])->name('tiket.gangguan.pelanggan.search');
    Route::get('/pelanggan/{id}/conn', [TiketGangguanController::class, 'pelangganConn'])->name('tiket.gangguan.pelanggan.conn');

    // Teknisi
    Route::get('/getTeknisi', [TiketGangguanController::class, 'getTeknisi'])->name('tiket.gangguan.getTeknisi');
});