<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes (public - no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Protected routes (require JWT authentication)
Route::middleware('jwt')->group(function () {
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => [
                'email' => $request->input('user_email'),
                'name' => $request->input('user_name'),
                'role' => $request->input('user_role'),
            ]
        ]);
    });
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Admin routes (require JWT + admin role)
Route::middleware(['jwt', 'admin'])->prefix('admin')->group(function () {
    Route::put('/users/role', [AdminController::class, 'updateRole']);
});

// Protected routes (require authentication - Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
