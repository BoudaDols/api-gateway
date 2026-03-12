<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private JWTService $jwtService
    ) {}

    /**
     * Handle user registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Create user with default 'user' role
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'user', // Always 'user' - admins created separately
        ]);

        // Generate JWT token with user data
        $token = $this->jwtService->generateToken([
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ]);

        // Return success response with token and user data
        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]
        ], 201);
    }

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Email is validated by LoginRequest (email format)
        $user = User::where('email', $request->validated()['email'])->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->validated()['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate JWT token with user data
        $token = $this->jwtService->generateToken([
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ]);

        // Return success response with token and user data
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]
        ]);
    }

    /**
     * Refresh an expired or about-to-expire token
     */
    public function refresh(Request $request): JsonResponse
    {
        // 1. Get token from Authorization header
        $oldToken = $request->bearerToken();
        
        if (!$oldToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided'
            ], 401);
        }
        
        // 2. Try to refresh the token
        $newToken = $this->jwtService->refreshToken($oldToken);
        
        if (!$newToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token cannot be refreshed. Please login again.'
            ], 401);
        }
        
        // 3. Return new token
        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ]
        ]);
    }
}
