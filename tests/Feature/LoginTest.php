<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'expires_in', 'user'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_returns_user_data(): void
    {
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ]);

        $response->assertJson([
            'data' => [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'role' => 'admin',
                ],
            ],
        ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Invalid credentials']);
    }

    public function test_login_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);
        $response->assertStatus(422);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_does_not_expose_whether_email_exists(): void
    {
        // Both unknown email and wrong password return the same message
        $unknownEmail = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        User::factory()->create([
            'email' => 'known@example.com',
            'password' => bcrypt('password123'),
        ]);

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertEquals(
            $unknownEmail->json('message'),
            $wrongPassword->json('message')
        );
    }
}
