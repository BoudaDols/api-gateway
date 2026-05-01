<?php

namespace Tests\Feature\V2;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private string $phone = '+1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log']);
    }

    // --- Step 1: register ---

    public function test_register_sends_otp_for_new_phone(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'phone' => $this->phone,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('phone_otps', ['phone' => $this->phone, 'type' => 'register']);
    }

    public function test_register_fails_for_already_registered_phone(): void
    {
        User::factory()->create(['phone' => $this->phone]);

        $response = $this->postJson('/api/v2/auth/register', [
            'phone' => $this->phone,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_register_fails_with_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'phone' => '1234567890', // missing +
            'name' => 'John Doe',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_without_name(): void
    {
        $response = $this->postJson('/api/v2/auth/register', [
            'phone' => $this->phone,
        ]);

        $response->assertStatus(422);
    }

    public function test_register_rate_limit_blocks_after_3_requests(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v2/auth/register', [
                'phone' => $this->phone,
                'name' => 'John Doe',
            ]);
        }

        $response = $this->postJson('/api/v2/auth/register', [
            'phone' => $this->phone,
            'name' => 'John Doe',
        ]);

        // Either OtpService rate limit (429) or throttle middleware (429)
        $response->assertStatus(429);
    }

    // --- Step 2: register/verify ---

    public function test_register_verify_creates_user_and_returns_token(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'register');

        $response = $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'token_type', 'expires_in', 'user'],
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', ['phone' => $this->phone, 'role' => 'user']);
    }

    public function test_register_verify_always_assigns_user_role(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'register');

        $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        $this->assertDatabaseHas('users', ['phone' => $this->phone, 'role' => 'user']);
    }

    public function test_register_verify_fails_with_wrong_otp(): void
    {
        app(OtpService::class)->generate($this->phone, 'register');

        $response = $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => '000000',
            'name' => 'John Doe',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_register_verify_fails_with_expired_otp(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'register');
        PhoneOtp::where('phone', $this->phone)->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(401);
    }

    public function test_register_verify_otp_is_single_use(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'register');

        $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        // Second use of same OTP
        $response = $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(401);
    }

    public function test_registered_user_token_works_on_protected_routes(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'register');

        $response = $this->postJson('/api/v2/auth/register/verify', [
            'phone' => $this->phone,
            'otp' => $code,
            'name' => 'John Doe',
        ]);

        $token = $response->json('data.token');

        $profile = $this->getJson('/api/profile', ['Authorization' => "Bearer $token"]);
        $profile->assertStatus(200);
    }
}
