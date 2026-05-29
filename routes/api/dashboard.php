<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Dashboard\DashboardController;

Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
