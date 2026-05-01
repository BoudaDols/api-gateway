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

    private function makeToken(array $overrides = []): string
    {
        return $this->jwt->generateToken(array_merge([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'user',
        ], $overrides));
    }

    public function test_valid_token_can_be_refreshed(): void
    {
        $token = $this->makeToken();

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['token', 'token_type', 'expires_in']])
            ->assertJson(['success' => true]);
    }

    public function test_refreshed_token_is_different_from_original(): void
    {
        $token = $this->makeToken();
        sleep(1); // ensure different iat/exp

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => "Bearer $token",
        ]);

        $this->assertNotEquals($token, $response->json('data.token'));
    }

    public function test_refresh_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Token not provided']);
    }

    public function test_refresh_fails_for_tampered_token(): void
    {
        $token = $this->makeToken();
        $parts = explode('.', $token);
        $parts[2] = 'badsignature';

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer '.implode('.', $parts),
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_refresh_fails_for_token_outside_refresh_window(): void
    {
        $token = $this->makeToken();
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['exp'] = time() - (config('jwt.refresh_ttl') * 60) - 1;
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $secret = config('jwt.secret');
        $parts[2] = rtrim(strtr(base64_encode(hash_hmac('sha256', "$parts[0].$parts[1]", $secret, true)), '+/', '-_'), '=');

        $response = $this->postJson('/api/auth/refresh', [], [
            'Authorization' => 'Bearer '.implode('.', $parts),
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
