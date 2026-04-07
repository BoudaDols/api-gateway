<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return app(JWTService::class)->generateToken([
            'email' => $user->email,
            'name'  => $user->name,
            'role'  => $user->role,
        ]);
    }

    public function test_profile_returns_user_data_with_valid_token(): void
    {
        $user  = User::factory()->create(['role' => 'admin']);
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/profile', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'email' => $user->email,
                    'name'  => $user->name,
                    'role'  => 'admin',
                ],
            ]);
    }

    public function test_profile_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Token not provided']);
    }

    public function test_profile_returns_401_with_invalid_token(): void
    {
        $response = $this->getJson('/api/profile', [
            'Authorization' => 'Bearer invalid.token.here',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid or expired token']);
    }

    public function test_profile_returns_401_with_tampered_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->tokenFor($user);
        $parts = explode('.', $token);
        $parts[2] = 'tampered';

        $response = $this->getJson('/api/profile', [
            'Authorization' => 'Bearer ' . implode('.', $parts),
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_returns_401_with_blacklisted_token(): void
    {
        $user  = User::factory()->create();
        $token = $this->tokenFor($user);

        // Blacklist the token
        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer $token"]);

        $response = $this->getJson('/api/profile', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Token has been revoked. Please login again.']);
    }

    public function test_profile_returns_401_with_malformed_authorization_header(): void
    {
        $response = $this->getJson('/api/profile', [
            'Authorization' => 'NotBearer sometoken',
        ]);

        $response->assertStatus(401);
    }
}
