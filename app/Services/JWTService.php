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
            'alg' => $this->algo
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
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
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
