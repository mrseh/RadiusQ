<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Profile\ProfileController;

Route::prefix('profile')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::get('/license', [ProfileController::class, 'license']);
    Route::put('/update', [ProfileController::class, 'update']);
    Route::get('/password', [ProfileController::class, 'passwordForm']);
    Route::put('/password', [ProfileController::class, 'changePassword']);
    Route::get('/license-renew', [ProfileController::class, 'licenseRenew']);
});
