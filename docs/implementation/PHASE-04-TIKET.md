# Fase 4 — Tiket Gangguan

**Endpoint:** 14 | **Controller:** `App\Http\Controllers\Api\Tiket\TiketGangguanController`
**Route file:** `routes/api/tiket.php`

---

Controller: `App\Http\Controllers\Api\Tiket\TiketGangguanController`
File: `app/Http/Controllers/Api\Tiket\TiketGangguanController.php` (311 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Tiket\TiketGangguanController;

// ─── Tiket Gangguan ─────────────────────────────────────────────────────────────
Route::prefix('gangguan')->group(function () {
    // List & stats
    Route::get('ajax',                  [TiketGangguanController::class, 'ajax']);
    Route::get('stats',                [TiketGangguanController::class, 'stats']);
    // Create
    Route::post('/',                    [TiketGangguanController::class, 'store']);
    // Actions
    Route::get('{id}/ambil',           [TiketGangguanController::class, 'ambil']);
    Route::get('{id}/tutup',           [TiketGangguanController::class, 'tutup']);
    // Bulk actions
    Route::post('bulk/open',           [TiketGangguanController::class, 'bulkOpen']);
    Route::post('bulk/progress',       [TiketGangguanController::class, 'bulkProgress']);
    Route::post('bulk/close',          [TiketGangguanController::class, 'bulkClose']);
    Route::post('bulk/delete',         [TiketGangguanController::class, 'bulkDelete']);
    // Detail
    Route::get('{id}/detail',          [TiketGangguanController::class, 'detail']);
    // Pelanggan
    Route::get('pelanggan/search',     [TiketGangguanController::class, 'pelangganSearch']);
    Route::get('pelanggan/{id}/conn',  [TiketGangguanController::class, 'pelangganConn']);
    // Teknisi
    Route::get('getTeknisi',           [TiketGangguanController::class, 'getTeknisi']);
});
```

**Catatan:**
- `Route::post('/', ...)` untuk store — tidak ada nama `create` atau `store` di spec, tapi controller perlu cek apakah ada method `store`.
- Jika controller tidak punya method `store`, rename atau tambahkan.

---

## Checklist Fase 4

```
□ routes/api/tiket.php tulis
□ Konfirmasi TiketGangguanController punya: ajax, stats, store, ambil, tutup, bulkOpen, bulkProgress, bulkClose, bulkDelete, detail, pelangganSearch, pelangganConn, getTeknisi
□ Jika tidak ada method store → rename ambil/tutup endpoint atau buat wrapper method
□ Test: php artisan route:list | grep tiket
```