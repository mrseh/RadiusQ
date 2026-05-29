# Fase 2 — Hotspot

**Endpoint:** 17 | **Controller:** `App\Http\Controllers\Api\Hotspot\*`
**Route file:** `routes/api/hotspot.php`

> Hotspot tidak ada di `routes-task.txt` versi awal, tapi ada di `all-routes-flat.json`.
> Asumsi: 17 endpoint sesuai dengan 5 file controller yang sudah ada.

---

## 2.1 Hotspot User (13 endpoint)

Controller: `App\Http\Controllers\Api\Hotspot\HotspotUserController`
File: `app/Http/Controllers/Api/Hotspot/HotspotUserController.php` (491 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Hotspot\HotspotUserController;
use App\Http\Controllers\Api\Hotspot\HotspotSessionController;
use App\Http\Controllers\Api\Hotspot\HotspotProfileController;
use App\Http\Controllers\Api\Hotspot\HotspotTemplateController;
use App\Http\Controllers\Api\Hotspot\HotspotVoucherController;

// ─── Hotspot User ──────────────────────────────────────────────────────────────
Route::prefix('user')->group(function () {
    Route::get('ajax',               [HotspotUserController::class, 'ajax']);
    Route::post('store',             [HotspotUserController::class, 'store']);
    Route::post('bulk-destroy',     [HotspotUserController::class, 'bulkDestroy']);
    Route::post('bulk-edit',       [HotspotUserController::class, 'bulkEdit']);
    Route::post('bulk-reset-mac',   [HotspotUserController::class, 'bulkResetMac']);
    Route::post('bulk-reset-counter', [HotspotUserController::class, 'bulkResetCounter']);
    Route::post('bulk-reactivate',  [HotspotUserController::class, 'bulkReactivate']);
    Route::post('generate-voucher', [HotspotUserController::class, 'generateVoucher']);
    Route::get('print',             [HotspotUserController::class, 'print']);
    Route::get('detail',            [HotspotUserController::class, 'detail']);
    Route::get('credentials',       [HotspotUserController::class, 'credentials']);
    Route::get('reseller',          [HotspotUserController::class, 'resellerMeta']);
    Route::get('stats',             [HotspotUserController::class, 'stats']);
    Route::match(['get', 'post'], 'setting', [HotspotUserController::class, 'setting']);
    Route::post('import',           [HotspotUserController::class, 'import']);
});
```

**Catatan: 13 endpoint Hotspot User (routes-task.txt + all-routes-flat.json)**
- routes-task.txt: ajax, store, bulk-destroy, bulk-edit, bulk-reset-mac, bulk-reset-counter, bulk-reactivate, generate-voucher, print, detail, credentials, reseller, stats, setting (GET+POST), import → **15 endpoint**
- all-routes-flat.json: tambahan reseller-meta & setting → sama

## 2.2 Hotspot Session (4 endpoint)

Controller: `App\Http\Controllers\Api\Hotspot\HotspotSessionController`
File: `app/Http/Controllers/Api/Hotspot/HotspotSessionController.php` (134 baris)

```php
// ─── Hotspot Session ──────────────────────────────────────────────────────────
Route::prefix('session')->group(function () {
    Route::get('ajax',                    [HotspotSessionController::class, 'ajax']);
    Route::delete('clear-session',        [HotspotSessionController::class, 'clearSession']);
    Route::post('kick-selected?_ids_=:ids', [HotspotSessionController::class, 'kickSelected'])
        ->name('hotspot.session.kick');
    Route::delete('delete-selected?_ids_=:ids', [HotspotSessionController::class, 'deleteSelected'])
        ->name('hotspot.session.delete');
});
```

**Catatan:** Query string `?_ids_=:ids` di route path — ini quirk dari frontend DataTables multi-select.
Apakah perlu ditangani di controller? Cek apakah `kickSelected` membaca `request('_ids_')`.

## 2.3 Hotspot Profile (3 endpoint)

Controller: `App\Http\Controllers\Api\Hotspot\HotspotProfileController`
File: `app/Http/Controllers/Api/Hotspot/HotspotProfileController.php` (98 baris)

```php
// ─── Hotspot Profile ───────────────────────────────────────────────────────────
Route::prefix('profile')->group(function () {
    Route::get('ajax',           [HotspotProfileController::class, 'ajax']);
    Route::post('store',        [HotspotProfileController::class, 'store']);
    Route::get('/',             [HotspotProfileController::class, 'index']);
    Route::put('{id}/toggle-status', [HotspotProfileController::class, 'toggleStatus']);
});
```

## 2.4 Hotspot Template (3 endpoint)

Controller: `App\Http\Controllers\Api\Hotspot\HotspotTemplateController`
File: `app/Http/Controllers/Api/Hotspot/HotspotTemplateController.php` (67 baris)

```php
// ─── Hotspot Template ──────────────────────────────────────────────────────────
Route::prefix('template')->group(function () {
    Route::get('ajax',  [HotspotTemplateController::class, 'ajax']);
    Route::post('store', [HotspotTemplateController::class, 'store']);
    Route::get('/',    [HotspotTemplateController::class, 'index']);
});
```

## 2.5 Hotspot Voucher / Sold (6 endpoint)

Controller: `App\Http\Controllers\Api\Hotspot\HotspotVoucherController`
File: `app/Http/Controllers/Api\Hotspot\HotspotVoucherController.php` (246 baris)

```php
// ─── Hotspot Voucher (Sold) ─────────────────────────────────────────────────────
Route::prefix('sold')->group(function () {
    Route::get('ajax',           [HotspotVoucherController::class, 'ajax']);
    Route::get('stats',         [HotspotVoucherController::class, 'stats']);
    Route::get('detail/{id}',   [HotspotVoucherController::class, 'detail']);
    Route::post('refund/{ids}', [HotspotVoucherController::class, 'refundSelected']);
    Route::get('export',        [HotspotVoucherController::class, 'export']);
    Route::post('rekap',        [HotspotVoucherController::class, 'rekap']);
    Route::delete('delete-expired', [HotspotVoucherController::class, 'deleteExpired']);
});
```

---

## Checklist Fase 2

```
□ Konfirmasi jumlah endpoint Hotspot User (15 di routes-task.txt)
□ Cek HotspotSessionController — kickSelected/deleteSelected baca _ids_ dari query string
□ Konfirmasi HotspotVoucherController refactor endpoint count (6, bukan 7)
□ Form Request sudah ada: StoreHotspotUserRequest, BulkDestroy*, BulkEdit*, BulkReactivate*, GenerateVoucher*, Import*
□ Test: php artisan route:list | grep hotspot
```