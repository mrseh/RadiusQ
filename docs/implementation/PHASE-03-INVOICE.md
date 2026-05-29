# Fase 3 — Invoice

**Endpoint:** 22 (Unpaid: 15 + Paid: 7) | **Controller:** `App\Http\Controllers\Api\Invoice\*`
**Route file:** `routes/api/invoice.php`

---

## 3.1 Invoice Unpaid (15 endpoint)

Controller: `App\Http\Controllers\Api\Invoice\UnpaidController`
File: `app/Http/Controllers/Api/Invoice/UnpaidController.php` (421 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Invoice\UnpaidController;
use App\Http\Controllers\Api\Invoice\PaidController;

// ─── Invoice Unpaid ─────────────────────────────────────────────────────────────
Route::prefix('unpaid')->group(function () {
    Route::get('ajax',                    [UnpaidController::class, 'ajax']);
    Route::post('manual',                 [UnpaidController::class, 'manual']);
    Route::post('pay-selected',           [UnpaidController::class, 'paySelectedUnpaid']);
    Route::post('send-selected',          [UnpaidController::class, 'sendSelectedUnpaid']);
    Route::post('send-manual',            [UnpaidController::class, 'sendManualUnpaid']);
    Route::get('export',                  [UnpaidController::class, 'export']);
    Route::get('print',                   [UnpaidController::class, 'print']);
    Route::get('detail/{id}',             [UnpaidController::class, 'detail']);
    Route::get('pelanggan/search',        [UnpaidController::class, 'pelangganSearch']);
    Route::get('pelanggan/{id}',          [UnpaidController::class, 'pelangganDetail']);
    Route::post('{id}/pay',               [UnpaidController::class, 'payOne']);
    Route::delete('delete-selected',     [UnpaidController::class, 'deleteSelected']);
    Route::post('{id}/save',              [UnpaidController::class, 'saveInvoice']);
    Route::post('merge-duplicates',       [UnpaidController::class, 'mergeDuplicateInvoice']);
});
```

**Catatan:**
- Endpoint `delete-selected` (DELETE) vs `pay-selected` (POST) — hati-hati naming clash.
- `pelanggan/search` → GET, `pelanggan/{id}` → GET — dua route bertubi-tubi, perlu unique prefix di `Route::prefix('unpaid')`.

## 3.2 Invoice Paid (7 endpoint)

Controller: `App\Http\Controllers\Api\Invoice\PaidController`
File: `app/Http/Controllers/Api/Invoice/PaidController.php` (183 baris, sudah terisi)

```php
// ─── Invoice Paid ──────────────────────────────────────────────────────────────
Route::prefix('paid')->group(function () {
    Route::get('ajax',            [PaidController::class, 'ajax']);
    Route::post('cancel-selected', [PaidController::class, 'cancelSelected']);
    Route::post('send-selected',  [PaidController::class, 'sendPaidSelected']);
    Route::delete('delete-selected', [PaidController::class, 'deleteSelected']);
    Route::get('export',          [PaidController::class, 'export']);
    Route::get('print',          [PaidController::class, 'print']);
    Route::get('detail/{id}',    [PaidController::class, 'detail']);
});
```

---

## Checklist Fase 3

```
□ routes/api/invoice.php tulis
□ Konfirmasi: UnpaidController sudah punya method manual, pelangganSearch, pelangganDetail, payOne, mergeDuplicateInvoice
□ Konfirmasi: PaidController sudah punya method cancelSelected, sendPaidSelected, deleteSelected
□ Form Request sudah ada: BulkActionInvoiceRequest, MergeDuplicateInvoicesRequest, PayInvoiceRequest, SaveInvoiceRequest, StoreManualInvoiceRequest
□ Test: php artisan route:list | grep invoice
```