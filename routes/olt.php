<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Olt\OltController;

Route::middleware('auth:sanctum')->prefix('olt')->group(function () {
    Route::get('/', [OltController::class, 'index'])->name('olt');
    Route::get('/ajax', [OltController::class, 'ajax'])->name('olt.ajax');
    Route::post('/', [OltController::class, 'store'])->name('olt.store');
    Route::get('/{id}', [OltController::class, 'show'])->name('olt.show');
    Route::put('/{id}', [OltController::class, 'update'])->name('olt.update');
    Route::delete('/{id}', [OltController::class, 'destroy'])->name('olt.destroy');
    Route::get('/status/{id}', [OltController::class, 'status'])->name('olt.status');
    Route::get('/{id}/hioso', [OltController::class, 'hioso'])->name('olt.hioso');
    Route::get('/{id}/hsgq', [OltController::class, 'hsgq'])->name('olt.hsgq');
});