<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes (public - no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
