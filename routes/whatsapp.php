<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsApp\WhatsAppController;

Route::middleware('auth:sanctum')->prefix('whatsapp')->group(function () {
    Route::get('/ajax', [WhatsAppController::class, 'ajax'])->name('whatsapp.ajax');
    Route::get('/devices', [WhatsAppController::class, 'devices'])->name('whatsapp.devices');
    Route::post('/broadcast', [WhatsAppController::class, 'broadcast'])->name('whatsapp.broadcast');
    Route::post('/resend', [WhatsAppController::class, 'resend'])->name('whatsapp.resend');
    Route::delete('/destroy-many', [WhatsAppController::class, 'destroyMany'])->name('whatsapp.destroy-many');
    Route::delete('/clear-all', [WhatsAppController::class, 'clearAll'])->name('whatsapp.clear-all');

    // FRWA endpoints
    Route::post('/frwa/status', [WhatsAppController::class, 'frwaStatus'])->name('whatsapp.frwa.status');
    Route::post('/frwa/restart', [WhatsAppController::class, 'frwaRestart'])->name('whatsapp.frwa.restart');
    Route::post('/frwa/scan-qr', [WhatsAppController::class, 'frwaScanQr'])->name('whatsapp.frwa.scan-qr');
    Route::post('/frwa/disconnect', [WhatsAppController::class, 'frwaDisconnect'])->name('whatsapp.frwa.disconnect');

    // Settings
    Route::post('/setting/save', [WhatsAppController::class, 'saveSettings'])->name('whatsapp.setting.save');
    Route::post('/whatsapp/setting', [WhatsAppController::class, 'getSettings'])->name('whatsapp.setting');

    // Templates
    Route::get('/template', [WhatsAppController::class, 'templateList'])->name('whatsapp.template');
    Route::post('/template', [WhatsAppController::class, 'saveTemplate'])->name('whatsapp.template.save');

    // Broadcast helpers
    Route::post('/broadcast/area-options', [WhatsAppController::class, 'broadcastAreaOptions'])->name('whatsapp.broadcast.area-options');
    Route::post('/broadcast/receiver-list', [WhatsAppController::class, 'receiverList'])->name('whatsapp.broadcast.receiver-list');
});