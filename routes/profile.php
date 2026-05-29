<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Profile\ProfileController;

Route::middleware('auth:sanctum')->prefix('profile/ajax')->group(function () {

    Route::get('/profile', [ProfileController::class, 'profile'])->name('profile.ajax.profile');
    Route::get('/license', [ProfileController::class, 'license'])->name('profile.ajax.license');
    Route::put('/update', [ProfileController::class, 'update'])->name('profile.ajax.update');
    Route::get('/password', [ProfileController::class, 'password'])->name('profile.ajax.password');
    Route::get('/license-renew', [ProfileController::class, 'licenseRenew'])->name('profile.ajax.license-renew');
});