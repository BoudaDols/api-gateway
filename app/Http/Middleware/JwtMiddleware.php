<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use App\Services\TokenBlacklistService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function __construct(
        private JWTService $jwtService,
        private TokenBlacklistService $blacklistService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get token from Authorization header
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided',
            ], 401);
        }

        // Validate token
        $payload = $this->jwtService->validateToken($token);

        if (! $payload) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Check token is not blacklisted (logged out)
        if ($this->blacklistService->isBlacklisted($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token has been revoked. Please login again.',
            ], 401);
        }

        // Attach user info to request (supports V1 email tokens and V2 phone tokens)
        $request->merge([
            'user_email' => $payload['email'] ?? null,
            'user_phone' => $payload['phone'] ?? null,
            'user_name' => $payload['name'],
            'user_role' => $payload['role'],
        ]);

        return $next($request);
    }
}
