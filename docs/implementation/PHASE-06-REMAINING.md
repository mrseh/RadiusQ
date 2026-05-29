# Fase 6 — Remaining Modules

**Endpoint:** 39 | **Route files:** `transaksi.php`, `payment-gateway.php`, `map.php`, `perusahaan.php`, `users.php`, `log.php`, `profile.php`, `dashboard.php`
**Effort:** Rendah | **Prioritas:** ⭐

> Modul-modul ini sudah ada controller-nya. Effort rendah karena route file kecil.

---

## 6.1 Transaksi (8 endpoint)

**Route file:** `routes/api/transaksi.php`
**Controller:** `App\Http\Controllers\Api\Transaksi\TransaksiController`
**File:** `app/Http/Controllers/Api/Transaksi/TransaksiController.php` (250 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Transaksi\TransaksiController;

Route::prefix('transaksi')->group(function () {
    Route::get('ajax',             [TransaksiController::class, 'ajax']);
    Route::post('store',          [TransaksiController::class, 'store']);
    Route::get('/',               [TransaksiController::class, 'index']);
    Route::get('export/csv',      [TransaksiController::class, 'exportCsv']);
    Route::get('report/daily',   [TransaksiController::class, 'reportDaily']);
    Route::get('report/monthly', [TransaksiController::class, 'reportMonthly']);
    Route::delete('empty',       [TransaksiController::class, 'empty']);
    Route::get('stats',          [TransaksiController::class, 'stats']);
});
```

---

## 6.2 Payment Gateway (8 endpoint)

**Route file:** `routes/api/payment-gateway.php`
**Controller:** `App\Http\Controllers\Api\PaymentGateway\PaymentGatewayController`
**⚠ Catatan:** `app/Http/Controllers/Api/PaymentGateway/` ada direktori tapi tidak ada file PHP.

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentGateway\PaymentGatewayController;

Route::prefix('payment-gateway')->group(function () {
    Route::get('ajax',              [PaymentGatewayController::class, 'ajax']);
    Route::match(['get', 'post'], 'setting', [PaymentGatewayController::class, 'setting']);
    Route::prefix('withdraw')->group(function () {
        Route::post('ajax',          [PaymentGatewayController::class, 'withdrawAjax']);
        Route::post('default-bank', [PaymentGatewayController::class, 'withdrawDefaultBank']);
        Route::post('available',    [PaymentGatewayController::class, 'withdrawAvailable']);
        Route::post('request-otp',  [PaymentGatewayController::class, 'requestOtp']);
        Route::post('confirm',      [PaymentGatewayController::class, 'withdrawConfirm']);
    });
});
```

**⚠ Task tambahan:** Buat `PaymentGatewayController` + Form Request (5 sudah ada di `app/Http/Requests/Payment/`).
**Form Request tersedia:** `GetPaymentGatewaySettingRequest`, `SavePaymentGatewaySettingRequest`, `SetDefaultBankRequest`, `WithdrawAjaxRequest`, `WithdrawConfirmRequest`, `WithdrawRequestOtpRequest` ✅

---

## 6.3 Map User + ODP (4 endpoint)

**Route file:** `routes/api/map.php`
**Controller:** `App\Http\Controllers\Api\Map\MapController`
**File:** `app/Http/Controllers/Api/Map/MapController.php` (208 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Map\MapController;

Route::prefix('map')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('options', [MapController::class, 'userOptions']);
        Route::get('data',   [MapController::class, 'userData']);
    });
    Route::prefix('odp')->group(function () {
        Route::get('options', [MapController::class, 'odpOptions']);
        Route::get('data',   [MapController::class, 'odpData']);
    });
});
```

**Catatan:** Cek apakah method di controller sesuai nama: `userOptions`/`odpOptions` vs `options` yang diswitch dengan parameter.

---

## 6.4 Perusahaan (7 endpoint)

**Route file:** `routes/api/perusahaan.php`
**Controller:** `App\Http\Controllers\Api\Perusahaan\PerusahaanController`
**File:** `app/Http/Controllers/Api/Perusahaan/PerusahaanController.php` (201 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Perusahaan\PerusahaanController;

Route::prefix('perusahaan')->group(function () {
    // Company
    Route::get('company/get',  [PerusahaanController::class, 'getCompany']);
    Route::post('company/save', [PerusahaanController::class, 'saveCompany']);
    // Bank accounts
    Route::get('bank/list',   [PerusahaanController::class, 'bankList']);
    Route::post('bank',       [PerusahaanController::class, 'storeBank']);
    Route::put('bank/{id}',  [PerusahaanController::class, 'updateBank']);
    Route::delete('bank/{id}', [PerusahaanController::class, 'deleteBank']);
    Route::put('bank/{id}/default', [PerusahaanController::class, 'setDefaultBank']);
});
```

**Form Request:** `SaveCompanyRequest`, `StoreBankAccountRequest`, `UpdateBankAccountRequest` ✅

---

## 6.5 Users (3 endpoint)

**Route file:** `routes/api/users.php`
**Controller:** `App\Http\Controllers\Api\Users\UsersController`
**File:** `app/Http/Controllers/Api/Users/UsersController.php` (153 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Users\UsersController;

Route::prefix('users')->group(function () {
    Route::get('ajax',  [UsersController::class, 'ajax']);
    Route::post('/',    [UsersController::class, 'store']);
    Route::get('/',     [UsersController::class, 'index']);
});
```

**Form Request:** `StoreUserRequest` ✅

---

## 6.6 Log Aplikasi (2 endpoint)

**Route file:** `routes/api/log.php`
**Controller:** `App\Http\Controllers\Api\Log\LogController`
**File:** `app/Http/Controllers/Api/Log/LogController.php` (84 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Log\LogController;

Route::prefix('log')->group(function () {
    Route::get('ajax',      [LogController::class, 'ajax']);
    Route::delete('clear-all', [LogController::class, 'clearAll']);
});
```

---

## 6.7 Profile (6 endpoint)

**Route file:** `routes/api/profile.php`
**Controller:** `App\Http\Controllers\Api\Profile\ProfileController`
**File:** `app/Http/Controllers/Api/Profile/ProfileController.php` (181 baris, sudah terisi)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Profile\ProfileController;

Route::prefix('profile/ajax')->group(function () {
    Route::get('profile',       [ProfileController::class, 'profile']);
    Route::get('license',       [ProfileController::class, 'license']);
    Route::put('update',       [ProfileController::class, 'update']);
    Route::get('password',     [ProfileController::class, 'password']);
    Route::get('license-renew', [ProfileController::class, 'licenseRenew']);
});
```

**Catatan:** `routes-task.txt` memiliki `GET /profile/ajax/password` — ini bukan endpoint password change,
melainkan "show password form" (GET). Update password sendiri perlu `PUT /profile/ajax/password` atau `POST`.
**Form Request:** `UpdateProfileRequest` ✅

---

## 6.8 Dashboard (1 endpoint)

**Route file:** `routes/api/dashboard.php`
**Controller:** menggunakan existing ProfileController (license-renew duplikat)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Profile\ProfileController;

// /profile/ajax/license-renew dipanggil juga dari dashboard
// Jika dashboard perlu data lebih dari license-renew, buat DashboardController
Route::get('dashboard', [ProfileController::class, 'licenseRenew']);
```

**⚠ Catatan:** Dashboard endpoint (license-renew) adalah duplikat dari Profile. Jika dashboard butuh data berbeda, buat `DashboardController` baru.

---

## Checklist Fase 6

```
□ routes/api/transaksi.php        → tulis (simpel)
□ routes/api/payment-gateway.php  → tulis + buat PaymentGatewayController
□ routes/api/map.php              → tulis (simpel)
□ routes/api/perusahaan.php       → tulis (simpel)
□ routes/api/users.php            → tulis (simpel)
□ routes/api/log.php              → tulis (simpel)
□ routes/api/profile.php          → tulis (simpel)
□ routes/api/dashboard.php        → tulis atau skip (duplikat)
□ Konfirmasi PaymentGatewayController: semua method withdraw*
□ Test: php artisan route:list | grep -E "transaksi|payment|map|perusahaan|users|log|profile|dashboard"
```