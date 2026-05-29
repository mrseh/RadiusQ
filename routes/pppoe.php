<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Pppoe\PppoeUserController;
use App\Http\Controllers\Api\Pppoe\PppoeOnlineController;
use App\Http\Controllers\Api\Pppoe\PppoeOfflineController;
use App\Http\Controllers\Api\Pppoe\PppoeProfileController;

Route::middleware('auth:sanctum')->prefix('pppoe')->group(function () {

    // ── User CRUD ────────────────────────────────────────────────────────────
    Route::get('/user/ajax', [PppoeUserController::class, 'ajax'])->name('pppoe.user.ajax');
    Route::get('/user/stats', [PppoeUserController::class, 'stats'])->name('pppoe.user.stats');
    Route::get('/user/graph/user-monthly', [PppoeUserController::class, 'userMonthlyGraph'])->name('pppoe.user.graph.user-monthly');
    Route::post('/user/odp-by-area', [PppoeUserController::class, 'odpByArea'])->name('pppoe.user.odp-by-area');
    Route::post('/user/store', [PppoeUserController::class, 'store'])->name('pppoe.user.store');
    Route::get('/user/detail/{id}', [PppoeUserController::class, 'detail'])->name('pppoe.user.detail');
    Route::put('/user/update/{id}', [PppoeUserController::class, 'update'])->name('pppoe.user.update');
    Route::post('/user/bulk-update', [PppoeUserController::class, 'bulkUpdate'])->name('pppoe.user.bulk-update');
    Route::post('/user/bulk-disable', [PppoeUserController::class, 'bulkDisable'])->name('pppoe.user.bulk-disable');
    Route::post('/user/bulk-enable', [PppoeUserController::class, 'bulkEnable'])->name('pppoe.user.bulk-enable');
    Route::post('/user/bulk-delete', [PppoeUserController::class, 'bulkDelete'])->name('pppoe.user.bulk-delete');
    Route::post('/user/bulk-suspend', [PppoeUserController::class, 'bulkSuspend'])->name('pppoe.user.bulk-suspend');
    Route::match(['get', 'post'], '/user/setting', [PppoeUserController::class, 'setting'])->name('pppoe.user.setting');
    Route::get('/user/print/kartu', [PppoeUserController::class, 'printKartu'])->name('pppoe.user.print.kartu');
    Route::get('/user/print/stiker', [PppoeUserController::class, 'printStiker'])->name('pppoe.user.print.stiker');
    Route::get('/user/session/{id}', [PppoeUserController::class, 'session'])->name('pppoe.user.session');
    Route::get('/user/traffic/{id}', [PppoeUserController::class, 'traffic'])->name('pppoe.user.traffic');
    Route::post('/user/import', [PppoeUserController::class, 'import'])->name('pppoe.user.import');
    Route::get('/user/export/xls', [PppoeUserController::class, 'exportXls'])->name('pppoe.user.export.xls');
    Route::get('/user/export/rsc', [PppoeUserController::class, 'exportRsc'])->name('pppoe.user.export.rsc');
    Route::post('/user/generate-kode-unik-all', [PppoeUserController::class, 'generateKodeUnikAll'])->name('pppoe.user.generate-kode-unik-all');

    // ── Online Sessions ───────────────────────────────────────────────────────
    Route::get('/online/ajax', [PppoeOnlineController::class, 'ajax'])->name('pppoe.online.ajax');

    // ── Offline Sessions ────────────────────────────────────────────────��────
    Route::get('/offline/ajax', [PppoeOfflineController::class, 'ajax'])->name('pppoe.offline.ajax');

    // ── Shared Session Management ───────────────────────────────────────────
    Route::delete('/session/clear-session', [PppoeOnlineController::class, 'clearSession'])->name('pppoe.session.clear-session');
    Route::post('/session/kick-selected', [PppoeOnlineController::class, 'kickSelected'])->name('pppoe.session.kick-selected');
    Route::delete('/session/delete-selected', [PppoeOnlineController::class, 'deleteSelected'])->name('pppoe.session.delete-selected');
    Route::get('/session/detail/{id}', [PppoeOnlineController::class, 'detail'])->name('pppoe.session.detail');

    // ── Profile Management ──────────────────────────────────────────────────
    Route::get('/profile/ajax', [PppoeProfileController::class, 'ajax'])->name('pppoe.profile.ajax');
    Route::post('/profile/store', [PppoeProfileController::class, 'store'])->name('pppoe.profile.store');
    Route::get('/profile', [PppoeProfileController::class, 'index'])->name('pppoe.profile');
    Route::put('/profile/{id}/toggle-status', [PppoeProfileController::class, 'toggleStatus'])->name('pppoe.profile.toggle-status');
});