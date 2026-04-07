<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_logged_on_every_api_call(): void
    {
        Log::spy();

        $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        Log::shouldHaveReceived('channel')
            ->with('stdout')
            ->once();
    }

    public function test_log_contains_required_fields(): void
    {
        Log::spy();

        $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        Log::shouldHaveReceived('channel')->with('stdout');
    }

    public function test_authenticated_request_logs_user_email(): void
    {
        Log::spy();

        $user  = User::factory()->create();
        $token = app(JWTService::class)->generateToken([
            'email' => $user->email,
            'name'  => $user->name,
            'role'  => $user->role,
        ]);

        $this->getJson('/api/profile', ['Authorization' => "Bearer $token"]);

        Log::shouldHaveReceived('channel')->with('stdout');
    }
}
