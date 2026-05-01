<?php

namespace App\Services;

use Exception;

class JWTService
{
    private string $secret;

    private int $ttl;

    private string $algo;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->ttl = config('jwt.ttl') * 60; // convert to seconds
        $this->algo = config('jwt.algo');
    }

    /**
     * Generate a JWT token from payload
     */
    public function generateToken(array $payload): string
    {
        // Create header
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]));

        // Add timestamps to payload
        $payload['iat'] = time(); // issued at
        $payload['exp'] = time() + $this->ttl; // expiration

        $payload = $this->base64UrlEncode(json_encode($payload));

        // Create signature
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    /**
     * Validate and decode a JWT token
     */
    public function validateToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;

            // Verify signature
            $validSignature = $this->base64UrlEncode(
                hash_hmac('sha256', "$header.$payload", $this->secret, true)
            );

            if ($signature !== $validSignature) {
                return null;
            }

            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payload), true);

            // Check expiration
            if (! isset($payload['exp']) || $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Refresh an expired or about-to-expire token
     */
    public function refreshToken(string $oldToken): ?string
    {
        // 1. Decode the old token (ignore expiration)
        $payload = $this->decodeToken($oldToken);

        if (! $payload) {
            return null; // Invalid token format
        }

        // 2. Verify signature is still valid
        if (! $this->verifySignature($oldToken)) {
            return null; // Tampered token
        }

        // 3. Check if token expired too long ago (refresh window)
        $refreshTtl = config('jwt.refresh_ttl') * 60; // Convert to seconds
        if (time() - $payload['exp'] > $refreshTtl) {
            return null; // Token too old to refresh
        }

        // 4. Generate new token preserving all user data (supports V1 email and V2 phone)
        $newPayload = array_filter([
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'name' => $payload['name'],
            'role' => $payload['role'],
        ], fn ($v) => $v !== null);

        return $this->generateToken($newPayload);
    }

    /**
     * Decode token without checking expiration
     */
    private function decodeToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;

            // Decode payload (don't check expiration)
            $decodedPayload = json_decode(
                $this->base64UrlDecode($payload),
                true
            );

            return $decodedPayload;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Verify token signature without checking expiration
     */
    private function verifySignature(string $token): bool
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return false;
            }

            [$header, $payload, $signature] = $parts;

            // Recalculate signature
            $validSignature = $this->base64UrlEncode(
                hash_hmac('sha256', "$header.$payload", $this->secret, true)
            );

            // Compare signatures (timing-safe comparison)
            return hash_equals($signature, $validSignature);
        } catch (Exception $e) {
            return false;
        }
    }
}
