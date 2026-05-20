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
        $this->jwt = new JWTService;
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

    public function test_generates_refresh_token(): void
    {
        $refreshToken = $this->jwt->generateRefreshToken([
            'id' => 'uuid-123',
            'email' => 'test@example.com',
            'name' => 'Test',
            'role' => 'user',
        ]);

        $this->assertEquals(64, strlen($refreshToken));
    }

    public function test_validate_refresh_token_returns_user_data(): void
    {
        $userData = ['id' => 'uuid-123', 'email' => 'test@example.com', 'name' => 'Test', 'role' => 'user'];
        $refreshToken = $this->jwt->generateRefreshToken($userData);

        $result = $this->jwt->validateRefreshToken($refreshToken);

        $this->assertNotNull($result);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('uuid-123', $result['id']);
    }

    public function test_validate_refresh_token_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->jwt->validateRefreshToken('invalid-random-string'));
    }

    public function test_revoke_refresh_token_invalidates_it(): void
    {
        $userData = ['id' => 'uuid-123', 'email' => 'test@example.com', 'name' => 'Test', 'role' => 'user'];
        $refreshToken = $this->jwt->generateRefreshToken($userData);

        $this->jwt->revokeRefreshToken($refreshToken);

        $this->assertNull($this->jwt->validateRefreshToken($refreshToken));
    }
}
