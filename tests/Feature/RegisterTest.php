<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'expires_in', 'user'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_register_creates_user_in_database(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_always_assigns_user_role(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertJson([
            'data' => ['user' => ['role' => 'user']],
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user',
        ]);
    }

    public function test_register_returns_token_immediately(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('Bearer', $response->json('data.token_type'));
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/register', $this->validPayload);
        $response->assertStatus(422);
    }

    public function test_register_fails_without_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_mismatched_passwords(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);
        $response->assertStatus(422);
    }

    public function test_register_ignores_role_in_request(): void
    {
        $this->postJson('/api/auth/register', array_merge($this->validPayload, ['role' => 'admin']));

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user',
        ]);
    }
}
