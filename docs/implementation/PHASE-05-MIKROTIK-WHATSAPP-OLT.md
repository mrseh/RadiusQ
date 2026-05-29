# Fase 5 — Mikrotik + WhatsApp + OLT

**Endpoint:** 32 (Mikrotik: 8 + WhatsApp: 17 + OLT: 7) | **3 Route files**
**Effort:** Menengah | **Prioritas:** ⭐⭐

---

## 5.1 Mikrotik (8 endpoint)

**Route file:** `routes/api/mikrotik.php`
**Controller:** `App\Http\Controllers\Api\Mikrotik\MikrotikController`
**File:** `app/Http/Controllers/Api/Mikrotik/MikrotikController.php` (413 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mikrotik\MikrotikController;

Route::prefix('mikrotik')->group(function () {
    Route::get('ajax',              [MikrotikController::class, 'ajax']);
    Route::post('store',           [MikrotikController::class, 'store']);
    Route::put('update/{id}',     [MikrotikController::class, 'update']);
    Route::get('show/{id}',        [MikrotikController::class, 'show']);
    Route::delete('destroy/{id}', [MikrotikController::class, 'destroy']);
    Route::post('probe/{id}',     [MikrotikController::class, 'probe']);
    Route::post('{id}/script',   [MikrotikController::class, 'script']);
});
```

**Form Request:** `StoreMikrotikRequest`, `UpdateMikrotikRequest`, `ExecuteMikrotikScriptRequest`

---

## 5.2 WhatsApp (17 endpoint)

**Route file:** `routes/api/whatsapp.php`
**Controller:** `App\Http\Controllers\Api\WhatsApp\WhatsAppController`
**File:** `app/Http/Controllers/Api/WhatsApp/WhatsAppController.php` (412 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsApp\WhatsAppController;

Route::prefix('whatsapp')->group(function () {
    // CRUD
    Route::get('ajax',           [WhatsAppController::class, 'ajax']);
    Route::get('devices',        [WhatsAppController::class, 'devices']);
    Route::delete('destroy-many', [WhatsAppController::class, 'destroyMany']);
    Route::delete('clear-all',   [WhatsAppController::class, 'clearAll']);
    // Messaging
    Route::post('broadcast',     [WhatsAppController::class, 'broadcast']);
    Route::post('resend',        [WhatsAppController::class, 'resend']);
    // FRWA (Free WhatsApp) device control
    Route::post('frwa/status',     [WhatsAppController::class, 'frwaStatus']);
    Route::post('frwa/restart',    [WhatsAppController::class, 'frwaRestart']);
    Route::post('frwa/scan-qr',    [WhatsAppController::class, 'frwaScanQr']);
    Route::post('frwa/disconnect', [WhatsAppController::class, 'frwaDisconnect']);
    // Settings
    Route::post('setting/save',  [WhatsAppController::class, 'saveSetting']);
    // Note: /whatsapp/whatsapp/setting — nested prefix, lihat catatan
    Route::match(['get', 'post'], 'whatsapp/setting', [WhatsAppController::class, 'setting']);
    // Template
    Route::match(['get', 'post'], 'template', [WhatsAppController::class, 'template']);
    // Broadcast helpers
    Route::post('broadcast/area-options',  [WhatsAppController::class, 'bcAreaOptions']);
    Route::post('broadcast/receiver-list', [WhatsAppController::class, 'bcReceiverList']);
});
```

**Catatan:**
- Route `/whatsapp/whatsapp/setting` (nested) — prefix kedua `whatsapp` muncul dua kali. Cek apakah ini typo atau memang diinginkan.
- `whatsapp/setting/save` → prefix sudah `whatsapp`, sehingga full path → `/whatsapp/setting/save` ✅
- `whatsapp/whatsapp/setting` → weird, tapi ikut spec. Konfirmasi dengan frontend.

---

## 5.3 OLT (7 endpoint)

**Route file:** `routes/api/olt.php`
**Controller:** `App\Http\Controllers\Api\Olt\OltController`
**File:** `app/Http/Controllers/Api/Olt/OltController.php` (297 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Olt\OltController;

Route::prefix('olt')->group(function () {
    Route::get('ajax',   [OltController::class, 'ajax']);
    Route::post('/',     [OltController::class, 'store']);
    Route::get('/',      [OltController::class, 'index']);
    Route::get('status', [OltController::class, 'oltStatus']);
    // Duplikat di spec: /olt & /olt/ajax muncul 2x — tidak masalah
    // HiOSO & HSGQ sub-pages
    Route::get('hioso', [OltController::class, 'hioso']);
    Route::get('hsgq',  [OltController::class, 'hsgq']);
});
```

**Catatan:**
- routes-task.txt memiliki duplikat: `GET /olt` dan `GET /olt/ajax` muncul 2x. Hilangkan duplikat.
- `/status` ada di spec tapi di bawah OLT → full path `/olt/status`. Konfirmasi apakah ini benar atau perlu `/olt/status` saja.

---

## Checklist Fase 5

```
□ routes/api/mikrotik.php tulis
□ routes/api/whatsapp.php tulis
□ routes/api/olt.php tulis
□ Konfirmasi WhatsAppController punya: frwaStatus, frwaRestart, frwaScanQr, frwaDisconnect, bcAreaOptions, bcReceiverList, setting (note: nested prefix)
□ Konfirmasi OltController punya: oltStatus, hioso, hsgq
□ Form Request Mikrotik sudah ada
□ Test: php artisan route:list | grep -E "mikrotik|whatsapp|olt"
```