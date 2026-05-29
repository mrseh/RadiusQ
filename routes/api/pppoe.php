<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Pppoe\PppoeUserController;
use App\Http\Controllers\Api\Pppoe\PppoeOnlineController;
use App\Http\Controllers\Api\Pppoe\PppoeOfflineController;
use App\Http\Controllers\Api\Pppoe\PppoeProfileController;

Route::prefix('pppoe')->group(function () {

    // ─── User (22 endpoints) ──────────────────────────────────────────────────
    Route::prefix('user')->group(function () {
        Route::get('ajax',                     [PppoeUserController::class, 'ajax']);
        Route::get('stats',                   [PppoeUserController::class, 'stats']);
        Route::get('graph/user-monthly',        [PppoeUserController::class, 'userMonthlyGraph']);
        Route::post('odp-by-area',            [PppoeUserController::class, 'odpByArea']);
        Route::post('store',                  [PppoeUserController::class, 'store']);
        Route::get('detail/{id}',             [PppoeUserController::class, 'detail']);
        Route::put('update/{id}',             [PppoeUserController::class, 'update']);
        Route::post('bulk-update',            [PppoeUserController::class, 'bulkUpdate']);
        Route::post('bulk-disable',           [PppoeUserController::class, 'bulkDisable']);
        Route::post('bulk-enable',            [PppoeUserController::class, 'bulkEnable']);
        Route::post('bulk-delete',            [PppoeUserController::class, 'bulkDelete']);
        Route::post('bulk-suspend',           [PppoeUserController::class, 'bulkSuspend']);
        Route::match(['get', 'post'], 'setting', [PppoeUserController::class, 'setting']);
        Route::get('print/kartu',             [PppoeUserController::class, 'printKartu']);
        Route::get('print/stiker',            [PppoeUserController::class, 'printStiker']);
        Route::get('session/{id}',            [PppoeUserController::class, 'session']);
        Route::get('traffic/{id}',            [PppoeUserController::class, 'traffic']);
        Route::post('import',                 [PppoeUserController::class, 'import']);
        Route::get('export/xls',              [PppoeUserController::class, 'exportXls']);
        Route::get('export/rsc',             [PppoeUserController::class, 'exportRsc']);
        Route::post('generate-kode-unik-all', [PppoeUserController::class, 'generateKodeUnikAll']);
    });

    // ─── Online (6 endpoints) ────────────────────────────────────────────────
    Route::prefix('online')->group(function () {
        Route::get('ajax',               [PppoeOnlineController::class, 'ajax']);
        Route::delete('clear-session',   [PppoeOnlineController::class, 'clearSession']);
        Route::post('kick-selected',     [PppoeOnlineController::class, 'kickSelected']);
        Route::delete('delete-selected', [PppoeOnlineController::class, 'deleteSelected']);
        Route::get('detail/{id}',       [PppoeOnlineController::class, 'detail']);
    });

    // ─── Offline (6 endpoints) ───────────────────────────────────────────────
    Route::prefix('offline')->group(function () {
        Route::get('ajax',               [PppoeOfflineController::class, 'ajax']);
        Route::delete('clear-session',   [PppoeOfflineController::class, 'clearSession']);
        Route::post('kick-selected',     [PppoeOfflineController::class, 'kickSelected']);
        Route::delete('delete-selected', [PppoeOfflineController::class, 'deleteSelected']);
        Route::get('detail/{id}',       [PppoeOfflineController::class, 'detail']);
    });

    // ─── Profile (4 endpoints) ──────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('ajax',                    [PppoeProfileController::class, 'ajax']);
        Route::post('store',                  [PppoeProfileController::class, 'store']);
        Route::get('/',                       [PppoeProfileController::class, 'index']);
        Route::put('{id}/toggle-status',      [PppoeProfileController::class, 'toggleStatus']);
    });

});
