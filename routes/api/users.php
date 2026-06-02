<?php

use App\Http\Controllers\Api\Users\UsersController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(function () {
    Route::get('/ajax', [UsersController::class, 'ajax']);
    Route::post('/', [UsersController::class, 'store']);
    Route::get('/', [UsersController::class, 'show']);
    Route::put('/{id}', [UsersController::class, 'update']);
    Route::delete('/{id}', [UsersController::class, 'destroy']);
});
