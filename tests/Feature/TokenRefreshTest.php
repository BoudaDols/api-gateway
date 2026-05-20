<?php

namespace Tests\Feature;

use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private JWTService $jwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = app(JWTService::class);
    }

    private function makeRefreshToken(array $userData = []): string
    {
        $data = array_merge([
            'id'    => 'test-uuid',
            'email' => 'test@example.com',
            'name'  => 'Test User',
            'role'  => 'user',
        ], $userData);

        return $this->jwt->generateRefreshToken($data);
    }

    public function test_valid_refresh_token_returns_new_access_token(): void
    {
        $refreshToken = $this->makeRefreshToken();

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['access_token', 'token_type', 'expires_in']])
            ->assertJson(['success' => true]);
    }

    public function test_refreshed_access_token_works_on_protected_routes(): void
    {
        $refreshToken = $this->makeRefreshToken();

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $accessToken = $response->json('data.access_token');

        $this->getJson('/api/profile', ['Authorization' => "Bearer $accessToken"])
            ->assertStatus(200);
    }

    public function test_refresh_fails_without_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Refresh token not provided']);
    }

    public function test_refresh_fails_with_invalid_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'invalid-random-string',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_refresh_fails_after_token_is_revoked(): void
    {
        $refreshToken = $this->makeRefreshToken();

        // Revoke it
        $this->jwt->revokeRefreshToken($refreshToken);

        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
