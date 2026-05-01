<?php

namespace Tests\Feature\V2;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private string $phone = '+1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log']);
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'phone' => $this->phone,
            'role' => 'user',
        ]);
    }

    // --- Step 1: login ---

    public function test_login_returns_success_for_registered_phone(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v2/auth/login', [
            'phone' => $this->phone,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_login_returns_same_response_for_unknown_phone(): void
    {
        $known = $this->postJson('/api/v2/auth/login', ['phone' => $this->phone]);

        $this->createUser();
        $unknown = $this->postJson('/api/v2/auth/login', ['phone' => '+9999999999']);

        $this->assertEquals($known->json('message'), $unknown->json('message'));
    }

    public function test_login_does_not_send_sms_for_unknown_phone(): void
    {
        // No user — OTP is generated but SMS is skipped
        $this->postJson('/api/v2/auth/login', ['phone' => $this->phone]);

        // OTP record exists but no user was found so SMS was not sent
        $this->assertDatabaseHas('phone_otps', ['phone' => $this->phone, 'type' => 'login']);
        $this->assertDatabaseMissing('users', ['phone' => $this->phone]);
    }

    public function test_login_fails_with_invalid_phone_format(): void
    {
        $response = $this->postJson('/api/v2/auth/login', [
            'phone' => 'not-a-phone',
        ]);

        $response->assertStatus(422);
    }

    // --- Step 2: login/verify ---

    public function test_login_verify_returns_token_for_valid_otp(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'token_type', 'expires_in', 'user'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_verify_fails_with_wrong_otp(): void
    {
        $this->createUser();
        app(OtpService::class)->generate($this->phone, 'login');

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => '000000',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_login_verify_fails_with_expired_otp(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');
        PhoneOtp::where('phone', $this->phone)->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $response->assertStatus(401);
    }

    public function test_login_verify_otp_is_single_use(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $response->assertStatus(401);
    }

    public function test_login_verify_fails_for_unknown_phone(): void
    {
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_login_verify_unknown_phone_returns_same_message_as_wrong_otp(): void
    {
        $this->createUser();
        app(OtpService::class)->generate($this->phone, 'login');

        $wrongOtp = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => '000000',
        ]);

        $code = app(OtpService::class)->generate('+9999999999', 'login');
        $unknownPhone = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => '+9999999999',
            'otp' => $code,
        ]);

        $this->assertEquals($wrongOtp->json('message'), $unknownPhone->json('message'));
    }

    public function test_token_from_login_works_on_protected_routes(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $response = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ]);

        $token = $response->json('data.token');

        $this->getJson('/api/profile', ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
    }

    public function test_v2_token_can_be_refreshed(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $token = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ])->json('data.token');

        $this->postJson('/api/auth/refresh', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_v2_token_can_be_logged_out(): void
    {
        $this->createUser();
        $code = app(OtpService::class)->generate($this->phone, 'login');

        $token = $this->postJson('/api/v2/auth/login/verify', [
            'phone' => $this->phone,
            'otp' => $code,
        ])->json('data.token');

        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);

        $this->getJson('/api/profile', ['Authorization' => "Bearer $token"])
            ->assertStatus(401);
    }
}
