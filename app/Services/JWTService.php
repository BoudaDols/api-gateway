<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class JWTService
{
    private string $secret;

    private int $ttl;

    private int $refreshTtl;

    private string $algo;

    private const REFRESH_PREFIX = 'refresh_token:';

    public function __construct()
    {
        $secret = config('jwt.secret');

        if (empty($secret)) {
            throw new \RuntimeException('JWT secret is not configured.');
        }

        $this->secret = $secret;
        $this->ttl = config('jwt.ttl') * 60; // access token TTL in seconds
        $this->refreshTtl = config('jwt.refresh_ttl') * 60; // refresh token TTL in seconds
        $this->algo = config('jwt.algo');
    }

    /**
     * Generate a short-lived access token (JWT).
     */
    public function generateToken(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algo,
        ]));

        $payload['iat'] = time();
        $payload['exp'] = time() + $this->ttl;

        $payload = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        // amazonq-ignore-next-line
        return "$header.$payload.$signature";
    }

    /**
     * Generate an opaque refresh token and store it in Redis.
     * The refresh token is a random string — not a JWT, not decodable.
     */
    public function generateRefreshToken(array $userData): string
    {
        $refreshToken = Str::random(64);

        // Store user data associated with this refresh token in Redis
        Cache::put(
            self::REFRESH_PREFIX . hash('sha256', $refreshToken),
            $userData,
            $this->refreshTtl
        );

        return $refreshToken;
    }

    /**
     * Validate a refresh token and return the associated user data.
     * Returns null if the token is invalid or expired.
     */
    public function validateRefreshToken(string $refreshToken): ?array
    {
        $key = self::REFRESH_PREFIX . hash('sha256', $refreshToken);

        return Cache::get($key);
    }

    /**
     * Revoke a refresh token (delete from Redis).
     */
    public function revokeRefreshToken(string $refreshToken): void
    {
        Cache::forget(self::REFRESH_PREFIX . hash('sha256', $refreshToken));
    }

    /**
     * Validate and decode a JWT access token.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;

            $validSignature = $this->base64UrlEncode(
                hash_hmac('sha256', "$header.$payload", $this->secret, true)
            );

            if (! hash_equals($validSignature, $signature)) {
                return null;
            }

            $payload = json_decode($this->base64UrlDecode($payload), true);

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
}
