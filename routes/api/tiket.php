<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tiket\TiketGangguanController;

Route::prefix('tiket/gangguan')->group(function () {
    Route::get('/ajax', [TiketGangguanController::class, 'ajax']);
    Route::get('/stats', [TiketGangguanController::class, 'stats']);
    Route::post('/', [TiketGangguanController::class, 'store']);
    Route::get('/{id}/ambil', [TiketGangguanController::class, 'ambil']);
    Route::get('/{id}/tutup', [TiketGangguanController::class, 'tutup']);
    Route::post('/bulk/open', [TiketGangguanController::class, 'bulkOpen']);
    Route::post('/bulk/progress', [TiketGangguanController::class, 'bulkProgress']);
    Route::post('/bulk/close', [TiketGangguanController::class, 'bulkClose']);
    Route::post('/bulk/delete', [TiketGangguanController::class, 'bulkDelete']);
    Route::get('/{id}/detail', [TiketGangguanController::class, 'detail']);
    Route::get('/pelanggan/search', [TiketGangguanController::class, 'pelangganSearch']);
    Route::get('/pelanggan/{id}/conn', [TiketGangguanController::class, 'pelangganConn']);
    Route::get('/getTeknisi', [TiketGangguanController::class, 'teknisiList']);
});
