# PHASE 01 — PPPoE Module

**Target:** 38 endpoint | **Effort:** Rendah | **Status:** Ready to Implement

> Semua controller sudah ada & terisi lengkap. Tinggal tulis route file.

---

## File: `routes/api/pppoe.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Pppoe\PppoeUserController;
use App\Http\Controllers\Api\Pppoe\PppoeOnlineController;
use App\Http\Controllers\Api\Pppoe\PppoeOfflineController;
use App\Http\Controllers\Api\Pppoe\PppoeProfileController;

Route::prefix('pppoe')->group(function () {

    // ── User (22 endpoint) ───────────────────────────────────────────────────
    Route::prefix('user')->group(function () {
        Route::get('ajax',                       [PppoeUserController::class, 'ajax']);
        Route::get('stats',                      [PppoeUserController::class, 'stats']);
        Route::get('graph/user-monthly',          [PppoeUserController::class, 'userMonthlyGraph']);
        Route::post('odp-by-area',               [PppoeUserController::class, 'odpByArea']);
        Route::post('store',                     [PppoeUserController::class, 'store']);
        Route::get('detail/{id}',                [PppoeUserController::class, 'detail']);
        Route::put('update/{id}',                [PppoeUserController::class, 'update']);
        Route::post('bulk-update',               [PppoeUserController::class, 'bulkUpdate']);
        Route::post('bulk-disable',              [PppoeUserController::class, 'bulkDisable']);
        Route::post('bulk-enable',               [PppoeUserController::class, 'bulkEnable']);
        Route::post('bulk-delete',               [PppoeUserController::class, 'bulkDelete']);
        Route::post('bulk-suspend',              [PppoeUserController::class, 'bulkSuspend']);
        Route::post('setting',                   [PppoeUserController::class, 'setting']);
        Route::get('print/kartu',                [PppoeUserController::class, 'printKartu']);
        Route::get('print/stiker',               [PppoeUserController::class, 'printStiker']);
        Route::get('session/{id}',               [PppoeUserController::class, 'session']);
        Route::get('traffic/{id}',               [PppoeUserController::class, 'traffic']);
        Route::post('import',                    [PppoeUserController::class, 'import']);
        Route::get('export/xls',                 [PppoeUserController::class, 'exportXls']);
        Route::get('export/rsc',                 [PppoeUserController::class, 'exportRsc']);
        Route::post('generate-kode-unik-all',    [PppoeUserController::class, 'generateKodeUnikAll']);
    });

    // ── Online (6 endpoint) ───────────────────────────────────────────────────
    Route::get('online/ajax',                    [PppoeOnlineController::class, 'ajax']);
    Route::get('session/online/detail/{id}',     [PppoeOnlineController::class, 'detail']);
    Route::delete('session/clear-session',       [PppoeOnlineController::class, 'clearSession']);
    Route::post('session/kick-selected',         [PppoeOnlineController::class, 'kickSelected']);
    Route::delete('session/delete-selected',     [PppoeOnlineController::class, 'deleteSelected']);

    // ── Offline (6 endpoint) ──────────────────────────────────────────────────
    Route::get('offline/ajax',                   [PppoeOfflineController::class, 'ajax']);
    Route::get('session/offline/detail/{id}',    [PppoeOfflineController::class, 'detail']);
    Route::delete('offline/clear-session',       [PppoeOfflineController::class, 'clearSession']);
    Route::post('offline/kick-selected',         [PppoeOfflineController::class, 'kickSelected']);
    Route::delete('offline/delete-selected',    [PppoeOfflineController::class, 'deleteSelected']);

    // ── Profile (4 endpoint) ──────────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('ajax',              [PppoeProfileController::class, 'ajax']);
        Route::post('store',            [PppoeProfileController::class, 'store']);
        Route::get('/',                 [PppoeProfileController::class, 'index']);
        Route::put('{id}/toggle-status', [PppoeProfileController::class, 'toggleStatus']);
    });
});
```

**Total: 38 endpoint**

---

## Verification

```bash
php artisan route:list --path=api/pppoe
```

---

## Catatan

- `routes-task.txt` ada duplicate `POST /pppoe/user/setting` → diskip yang kedua
- `routes-task.txt` memiliki `GET /pppoe/user/graph/user-monthly` → method controller adalah `userMonthlyGraph`
- Session offline/online menggunakan controller berbeda tapi endpoint prefix beda (`online/` vs `offline/`)
- Form Request: `StorePppoeUserRequest`, `StorePppoeProfileRequest`, `BulkActionRequest` (cek di `app/Http/Requests/Pppoe/`)
