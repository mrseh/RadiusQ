<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Users\UsersController;

Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/', [UsersController::class, 'index'])->name('users');
    Route::get('/ajax', [UsersController::class, 'ajax'])->name('users.ajax');
    Route::post('/', [UsersController::class, 'store'])->name('users.store');
    Route::get('/{id}', [UsersController::class, 'show'])->name('users.show');
    Route::put('/{id}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/{id}', [UsersController::class, 'destroy'])->name('users.destroy');
});