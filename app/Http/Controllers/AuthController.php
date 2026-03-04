<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private JWTService $jwtService
    ) {}

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
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
                'expires_in' => config('jwt.ttl') * 60, // in seconds
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]
        ]);
    }
}
