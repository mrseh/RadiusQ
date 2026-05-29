<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\UserController;

Route::prefix('users')->group(function () {
    Route::get('/ajax', [UserController::class, 'ajax']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});
