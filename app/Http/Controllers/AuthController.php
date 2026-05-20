<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\JWTService;
use App\Services\KafkaProducer;
use App\Services\TokenBlacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private JWTService $jwtService,
        private TokenBlacklistService $blacklistService,
        private KafkaProducer $kafka,
    ) {}

    /**
     * Handle user registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'user',
        ]);

        $userData = [
            'id'    => $user->uuid,
            'email' => $user->email,
            'name'  => $user->name,
            'role'  => $user->role,
        ];

        $accessToken = $this->jwtService->generateToken($userData);
        $refreshToken = $this->jwtService->generateRefreshToken($userData);

        // Publish user.registered event
        $this->kafka->publish('user.registered', [
            'event'         => 'user.registered',
            'user_id'       => $user->uuid,
            'user_email'    => $user->email,
            'user_name'     => $user->name,
            'registered_at' => now()->toIso8601String(),
        ], $user->uuid);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => config('jwt.ttl') * 60,
                'user' => [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
            ],
        ], 201);
    }

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated()['email'])->first();

        if (! $user || ! Hash::check($request->validated()['password'], $user->password)) {
            $this->kafka->publish('user.login', [
                'event'     => 'user.login_failed',
                'email'     => $request->validated()['email'],
                'reason'    => 'Invalid credentials',
                'ip'        => $request->ip(),
                'failed_at' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $userData = [
            'id'    => $user->uuid,
            'email' => $user->email,
            'name'  => $user->name,
            'role'  => $user->role,
        ];

        $accessToken = $this->jwtService->generateToken($userData);
        $refreshToken = $this->jwtService->generateRefreshToken($userData);

        // Publish login success event
        $this->kafka->publish('user.login', [
            'event'        => 'user.login_success',
            'user_id'      => $user->uuid,
            'user_email'   => $user->email,
            'user_name'    => $user->name,
            'ip'           => $request->ip(),
            'logged_in_at' => now()->toIso8601String(),
        ], $user->uuid);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => config('jwt.ttl') * 60,
                'user' => [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
            ],
        ]);
    }

    /**
     * Logout — blacklist the access token and revoke the refresh token.
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        $payload = $this->jwtService->validateToken($accessToken);

        // Blacklist the access token so it can't be reused
        $this->blacklistService->blacklist($accessToken, $payload['exp']);

        // Revoke the refresh token if provided in the request body
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            $this->jwtService->revokeRefreshToken($refreshToken);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh — exchange a valid refresh token for a new access token.
     * The refresh token itself stays valid until it expires or the user logs out.
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        if (! $refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token not provided',
            ], 401);
        }

        // Validate the refresh token (checks Redis)
        $userData = $this->jwtService->validateRefreshToken($refreshToken);

        if (! $userData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token. Please login again.',
            ], 401);
        }

        // Generate a new access token with the stored user data
        $newAccessToken = $this->jwtService->generateToken($userData);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'access_token' => $newAccessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
        ]);
    }
}
