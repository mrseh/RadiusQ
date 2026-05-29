<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsApp\WhatsAppController;
// use App\Http\Controllers\Api\WhatsApp\WhatsAppTemplateController; // pending implementation

Route::prefix('whatsapp')->group(function () {
    Route::get('/ajax', [WhatsAppController::class, 'ajax']);
    Route::get('/devices', [WhatsAppController::class, 'devices']);
    Route::post('/broadcast', [WhatsAppController::class, 'broadcast']);
    Route::post('/resend', [WhatsAppController::class, 'resend']);
    Route::delete('/destroy-many', [WhatsAppController::class, 'destroyMany']);
    Route::delete('/clear-all', [WhatsAppController::class, 'clearAll']);

    // FRWA
    Route::post('/frwa/status', [WhatsAppController::class, 'frwaStatus']);
    Route::post('/frwa/restart', [WhatsAppController::class, 'frwaRestart']);
    Route::post('/frwa/scan-qr', [WhatsAppController::class, 'frwaScanQr']);
    Route::post('/frwa/disconnect', [WhatsAppController::class, 'frwaDisconnect']);

    // Settings
    Route::post('/setting/save', [WhatsAppController::class, 'saveSetting']);
    Route::post('/setting', [WhatsAppController::class, 'settingGet']); // GET-style but POST body

    // Template — pending WhatsAppTemplateController
    // Route::get('/template', [WhatsAppTemplateController::class, 'index']);
    // Route::post('/template', [WhatsAppTemplateController::class, 'store']);

    // Broadcast helpers
    Route::post('/broadcast/area-options', [WhatsAppController::class, 'bcAreaOptions']);
    Route::post('/broadcast/receiver-list', [WhatsAppController::class, 'bcReceiverList']);
});
