<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('register');
    }

    public function test_login_rate_limit_blocks_after_5_attempts(): void
    {
        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429)
            ->assertJson(['success' => false, 'message' => 'Too many requests. Please slow down.']);
    }

    public function test_register_rate_limit_blocks_after_10_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/register', [
                'name' => "User $i",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $response = $this->postJson('/api/auth/register', [
            'name' => 'User 11',
            'email' => 'user11@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_rate_limit_response_is_json_not_html(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }
}
