<?php

namespace Tests\Unit;

use App\Services\JWTService;
use Tests\TestCase;

class JWTServiceTest extends TestCase
{
    private JWTService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JWTService();
    }

    public function test_generates_token_with_three_parts(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        $this->assertCount(3, explode('.', $token));
    }

    public function test_validates_valid_token(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        $payload = $this->jwt->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertEquals('Test', $payload['name']);
        $this->assertEquals('user', $payload['role']);
    }

    public function test_validate_returns_null_for_tampered_token(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        $parts = explode('.', $token);
        $parts[2] = 'invalidsignature';
        $tampered = implode('.', $parts);

        $this->assertNull($this->jwt->validateToken($tampered));
    }

    public function test_validate_returns_null_for_expired_token(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);

        // Manually decode and rebuild with past expiry
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['exp'] = time() - 3600;
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $expiredToken = implode('.', $parts);

        $this->assertNull($this->jwt->validateToken($expiredToken));
    }

    public function test_validate_returns_null_for_malformed_token(): void
    {
        $this->assertNull($this->jwt->validateToken('not.a.valid.jwt.token'));
        $this->assertNull($this->jwt->validateToken('invalid'));
        $this->assertNull($this->jwt->validateToken(''));
    }

    public function test_token_payload_contains_iat_and_exp(): void
    {
        $before = time();
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        $payload = $this->jwt->validateToken($token);

        $this->assertGreaterThanOrEqual($before, $payload['iat']);
        $this->assertGreaterThan(time(), $payload['exp']);
    }

    public function test_generates_new_token_on_refresh(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        sleep(1); // ensure different iat/exp
        $newToken = $this->jwt->refreshToken($token);

        $this->assertNotNull($newToken);
        $this->assertNotEquals($token, $newToken);
    }

    public function test_refresh_preserves_user_data(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'admin']);
        $newToken = $this->jwt->refreshToken($token);
        $payload = $this->jwt->validateToken($newToken);

        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertEquals('Test', $payload['name']);
        $this->assertEquals('admin', $payload['role']);
    }

    public function test_refresh_returns_null_for_tampered_token(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);
        $parts = explode('.', $token);
        $parts[2] = 'badsignature';
        $tampered = implode('.', $parts);

        $this->assertNull($this->jwt->refreshToken($tampered));
    }

    public function test_refresh_returns_null_for_token_outside_refresh_window(): void
    {
        $token = $this->jwt->generateToken(['email' => 'test@example.com', 'name' => 'Test', 'role' => 'user']);

        // Rebuild token with exp way in the past (beyond refresh window)
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['exp'] = time() - (config('jwt.refresh_ttl') * 60) - 1;
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        // Recalculate signature
        $secret = config('jwt.secret');
        $parts[2] = rtrim(strtr(base64_encode(hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true)), '+/', '-_'), '=');
        $oldToken = implode('.', $parts);

        $this->assertNull($this->jwt->refreshToken($oldToken));
    }
}
