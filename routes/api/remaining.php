<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Transaksi\TransaksiController;
use App\Http\Controllers\Api\Payment\PaymentGatewayController;
use App\Http\Controllers\Api\Perusahaan\PerusahaanController;
use App\Http\Controllers\Api\Users\UsersController;
use App\Http\Controllers\Api\Log\LogController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Dashboard\DashboardController;

/*
|--------------------------------------------------------------------------
| Transaksi Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/transaksi/ajax',              [TransaksiController::class, 'ajax']);
Route::post('/transaksi/store',             [TransaksiController::class, 'store']);
Route::get('/transaksi',                    [TransaksiController::class, 'index']);
Route::get('/transaksi/export/csv',         [TransaksiController::class, 'exportCsv']);
Route::get('/transaksi/report/daily',       [TransaksiController::class, 'reportDaily']);
Route::get('/transaksi/report/monthly',     [TransaksiController::class, 'reportMonthly']);
Route::delete('/transaksi/empty',           [TransaksiController::class, 'empty']);
Route::get('/transaksi/stats',              [TransaksiController::class, 'stats']);

/*
|--------------------------------------------------------------------------
| Payment Gateway Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/payment-gateway/ajax',                     [PaymentGatewayController::class, 'ajax']);
Route::match(['get', 'post'], '/payment-gateway/setting', [PaymentGatewayController::class, 'setting']);
Route::post('/payment-gateway/withdraw/ajax',            [PaymentGatewayController::class, 'withdrawAjax']);
Route::post('/payment-gateway/withdraw/default-bank',   [PaymentGatewayController::class, 'withdrawDefaultBank']);
Route::post('/payment-gateway/withdraw/available',       [PaymentGatewayController::class, 'withdrawAvailable']);
Route::post('/payment-gateway/withdraw/request-otp',  [PaymentGatewayController::class, 'withdrawRequestOtp']);
Route::post('/payment-gateway/withdraw/confirm',        [PaymentGatewayController::class, 'withdrawConfirm']);

/*
|--------------------------------------------------------------------------
| Perusahaan Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/perusahaan/company/get',        [PerusahaanController::class, 'getCompany']);
Route::post('/perusahaan/company/save',       [PerusahaanController::class, 'saveCompany']);
Route::get('/perusahaan/bank/list',           [PerusahaanController::class, 'listBank']);
Route::post('/perusahaan/bank',               [PerusahaanController::class, 'storeBank']);
Route::put('/perusahaan/bank/{id}',           [PerusahaanController::class, 'updateBank']);
Route::delete('/perusahaan/bank/{id}',        [PerusahaanController::class, 'destroyBank']);
Route::put('/perusahaan/bank/{id}/default',   [PerusahaanController::class, 'setDefaultBank']);

/*
|--------------------------------------------------------------------------
| Users Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/users/ajax',  [UsersController::class, 'ajax']);
Route::post('/users',      [UsersController::class, 'store']);
Route::get('/users',       [UsersController::class, 'index']);
Route::put('/users/{id}',  [UsersController::class, 'update']);
Route::delete('/users/{id}', [UsersController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| Log Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/log/ajax',        [LogController::class, 'ajax']);
Route::delete('/log/clear-all', [LogController::class, 'clearAll']);

/*
|--------------------------------------------------------------------------
| Profile Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/profile/ajax/profile',        [ProfileController::class, 'profile']);
Route::get('/profile/ajax/license',         [ProfileController::class, 'license']);
Route::put('/profile/ajax/update',          [ProfileController::class, 'update']);
Route::get('/profile/ajax/password',        [ProfileController::class, 'password']);
Route::get('/profile/ajax/license-renew',   [ProfileController::class, 'licenseRenew']);

/*
|--------------------------------------------------------------------------
| Dashboard Routes — Phase 6
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index']);