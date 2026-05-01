<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(string $role = 'user'): string
    {
        $user = User::factory()->create(['role' => $role]);

        return app(JWTService::class)->generateToken([
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ]);
    }

    public function test_user_can_logout_with_valid_token(): void
    {
        $token = $this->getToken();

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function test_token_is_rejected_after_logout(): void
    {
        $token = $this->getToken();

        // Logout
        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer $token"]);

        // Try to use the same token
        $response = $this->getJson('/api/profile', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_logout_requires_valid_token(): void
    {
        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer invalid.token.here',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout_requires_token(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }

    public function test_token_is_blacklisted_in_database_after_logout(): void
    {
        $token = $this->getToken();

        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer $token"]);

        $this->assertDatabaseHas('token_blacklist', ['token' => $token]);
    }
}
