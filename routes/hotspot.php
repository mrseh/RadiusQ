<?php

use App\Http\Controllers\Api\Hotspot\HotspotProfileController;
use App\Http\Controllers\Api\Hotspot\HotspotSessionController;
use App\Http\Controllers\Api\Hotspot\HotspotTemplateController;
use App\Http\Controllers\Api\Hotspot\HotspotUserController;
use App\Http\Controllers\Api\Hotspot\HotspotVoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('hotspot')->group(function () {

    // ─── Hotspot User ────────────────────────────────────────────
    Route::get('/user/ajax', [HotspotUserController::class, 'ajax']);
    Route::post('/user/store', [HotspotUserController::class, 'store']);
    Route::post('/user/bulk-destroy', [HotspotUserController::class, 'bulkDestroy']);
    Route::post('/user/bulk-edit', [HotspotUserController::class, 'bulkEdit']);
    Route::post('/user/bulk-reset-mac', [HotspotUserController::class, 'bulkResetMac']);
    Route::post('/user/bulk-reset-counter', [HotspotUserController::class, 'bulkResetCounter']);
    Route::post('/user/bulk-reactivate', [HotspotUserController::class, 'bulkReactivate']);
    Route::post('/user/generate-voucher', [HotspotUserController::class, 'generateVoucher']);
    Route::get('/user/print', [HotspotUserController::class, 'print']);
    Route::get('/user/detail', [HotspotUserController::class, 'detail']);
    Route::get('/user/credentials', [HotspotUserController::class, 'credentials']);
    Route::get('/user/reseller', [HotspotUserController::class, 'resellerMeta']);
    Route::get('/user/stats', [HotspotUserController::class, 'stats']);
    Route::match(['get', 'post'], '/user/setting', [HotspotUserController::class, 'setting']);
    Route::post('/user/import', [HotspotUserController::class, 'import']);

    // ─── Hotspot Session ────────────────────────────────────────
    Route::get('/session/ajax', [HotspotSessionController::class, 'ajax']);
    Route::delete('/session/clear-session', [HotspotSessionController::class, 'clearSession']);
    Route::post('/session/kick-selected', [HotspotSessionController::class, 'kickSelected']);
    Route::delete('/session/delete-selected', [HotspotSessionController::class, 'deleteSelected']);

    // ─── Hotspot Profile ────────────────────────────────────────
    Route::get('/profile/ajax', [HotspotProfileController::class, 'ajax']);
    Route::post('/profile/store', [HotspotProfileController::class, 'store']);
    Route::get('/profile', [HotspotProfileController::class, 'show']);
    Route::put('/profile/{id}/toggle-status', [HotspotProfileController::class, 'toggleStatus']);

    // ─── Hotspot Template ───────────────────────────────────────
    Route::get('/template/ajax', [HotspotTemplateController::class, 'ajax']);
    Route::post('/template/store', [HotspotTemplateController::class, 'store']);
    Route::get('/template', [HotspotTemplateController::class, 'show']);

    // ─── Hotspot Voucher (Sold) ──────────────────────────────────
    Route::prefix('sold')->group(function () {
        Route::get('/ajax', [HotspotVoucherController::class, 'ajax']);
        Route::get('/stats', [HotspotVoucherController::class, 'stats']);
        Route::get('/detail/{id}', [HotspotVoucherController::class, 'detail']);
        Route::post('/refund/{ids}', [HotspotVoucherController::class, 'refund']);
        Route::get('/export', [HotspotVoucherController::class, 'export']);
        Route::post('/rekap', [HotspotVoucherController::class, 'rekap']);
        Route::delete('/delete-expired', [HotspotVoucherController::class, 'deleteExpired']);
    });
});