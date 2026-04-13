<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\V2\AuthController as V2AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes (public - no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('refresh', [AuthController::class, 'refresh']);
});

// Protected routes (require JWT authentication)
Route::middleware(['jwt', 'throttle:api'])->group(function () {
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
Route::middleware(['jwt', 'admin', 'throttle:api'])->prefix('admin')->group(function () {
    Route::put('/users/role', [AdminController::class, 'updateRole']);
});

// Service proxy routes (require JWT authentication)
Route::middleware(['jwt', 'throttle:api'])
    ->group(function () {
        Route::any('/services/{service}/{path}', [GatewayController::class, 'proxy'])
            ->where('path', '.*');
    });

// V2 Auth routes (phone + OTP)
Route::prefix('v2/auth')->group(function () {
    Route::post('register',        [V2AuthController::class, 'register'])
        ->middleware('throttle:otp')
        ->name('v2.auth.register');
    Route::post('register/verify', [V2AuthController::class, 'registerVerify'])
        ->name('v2.auth.register.verify');
    Route::post('login',           [V2AuthController::class, 'login'])
        ->middleware('throttle:otp')
        ->name('v2.auth.login');
    Route::post('login/verify',    [V2AuthController::class, 'loginVerify'])
        ->name('v2.auth.login.verify');
});
