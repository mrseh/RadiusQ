<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Hotspot\HotspotUserController;
use App\Http\Controllers\Api\Hotspot\HotspotSessionController;
use App\Http\Controllers\Api\Hotspot\HotspotProfileController;
use App\Http\Controllers\Api\Hotspot\HotspotTemplateController;
use App\Http\Controllers\Api\Hotspot\HotspotVoucherController;

Route::prefix('hotspot')->group(function () {
    // User
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
    Route::post('/user/setting', [HotspotUserController::class, 'setting'])->name('hotspot.user.setting');
    Route::post('/user/import', [HotspotUserController::class, 'import']);

    // Session
    Route::get('/session/ajax', [HotspotSessionController::class, 'ajax']);
    Route::delete('/session/clear-session', [HotspotSessionController::class, 'clearSession']);
    Route::post('/session/kick-selected', [HotspotSessionController::class, 'kickSelected']);
    Route::delete('/session/delete-selected', [HotspotSessionController::class, 'deleteSelected']);

    // Profile
    Route::get('/profile/ajax', [HotspotProfileController::class, 'ajax']);
    Route::post('/profile/store', [HotspotProfileController::class, 'store']);
    Route::get('/profile', [HotspotProfileController::class, 'show']);
    Route::put('/profile/{id}/toggle-status', [HotspotProfileController::class, 'toggleStatus']);

    // Template
    Route::get('/template/ajax', [HotspotTemplateController::class, 'ajax']);
    Route::post('/template/store', [HotspotTemplateController::class, 'store']);
    Route::get('/template', [HotspotTemplateController::class, 'show']);

    // Voucher / Sold
    Route::get('/sold/ajax', [HotspotVoucherController::class, 'ajax']);
    Route::get('/sold/stats', [HotspotVoucherController::class, 'stats']);
    Route::get('/sold/detail/{id}', [HotspotVoucherController::class, 'detail']);
    Route::post('/sold/refund/{ids}', [HotspotVoucherController::class, 'refund']);
    Route::get('/sold/export', [HotspotVoucherController::class, 'export']);
    Route::post('/sold/rekap', [HotspotVoucherController::class, 'rekap']);
    Route::delete('/sold/delete-expired', [HotspotVoucherController::class, 'deleteExpired']);
});
