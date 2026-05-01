<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return app(JWTService::class)->generateToken([
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ]);
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);
        $token = $this->tokenFor($admin);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => $target->email,
            'role' => 'admin',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['email' => $target->email, 'role' => 'admin'],
            ]);

        $this->assertDatabaseHas('users', ['email' => $target->email, 'role' => 'admin']);
    }

    public function test_non_admin_cannot_update_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $target = User::factory()->create(['role' => 'user']);
        $token = $this->tokenFor($user);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => $target->email,
            'role' => 'admin',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Admin access required']);
    }

    public function test_update_role_fails_with_invalid_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $this->tokenFor($admin);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => 'nonexistent@example.com',
            'role' => 'admin',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422);
    }

    public function test_update_role_fails_with_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);
        $token = $this->tokenFor($admin);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => $target->email,
            'role' => 'superadmin',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422);
    }

    public function test_update_role_requires_authentication(): void
    {
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => $target->email,
            'role' => 'admin',
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_demote_admin_to_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);
        $token = $this->tokenFor($admin);

        $response = $this->putJson('/api/admin/users/role', [
            'email' => $target->email,
            'role' => 'user',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['email' => $target->email, 'role' => 'user']);
    }
}
