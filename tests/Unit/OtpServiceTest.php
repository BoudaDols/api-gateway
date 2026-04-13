<?php

namespace Tests\Unit;

use App\Models\PhoneOtp;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OtpService();
    }

    public function test_generate_creates_otp_in_database(): void
    {
        $this->service->generate('+1234567890', 'register');
        $this->assertDatabaseHas('phone_otps', ['phone' => '+1234567890', 'type' => 'register']);
    }

    public function test_generate_returns_6_digit_code(): void
    {
        $code = $this->service->generate('+1234567890', 'register');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_generate_deletes_previous_unused_otp(): void
    {
        $this->service->generate('+1234567890', 'register');
        $this->service->generate('+1234567890', 'register');

        $this->assertEquals(1, PhoneOtp::where('phone', '+1234567890')->count());
    }

    public function test_verify_returns_true_for_correct_code(): void
    {
        $code = $this->service->generate('+1234567890', 'register');
        $this->assertTrue($this->service->verify('+1234567890', $code, 'register'));
    }

    public function test_verify_returns_false_for_wrong_code(): void
    {
        $this->service->generate('+1234567890', 'register');
        $this->assertFalse($this->service->verify('+1234567890', '000000', 'register'));
    }

    public function test_verify_returns_false_for_expired_otp(): void
    {
        $code = $this->service->generate('+1234567890', 'register');

        PhoneOtp::where('phone', '+1234567890')->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($this->service->verify('+1234567890', $code, 'register'));
    }

    public function test_verify_is_single_use(): void
    {
        $code = $this->service->generate('+1234567890', 'register');

        $this->assertTrue($this->service->verify('+1234567890', $code, 'register'));
        $this->assertFalse($this->service->verify('+1234567890', $code, 'register'));
    }

    public function test_verify_returns_false_after_max_attempts(): void
    {
        $code = $this->service->generate('+1234567890', 'register');

        // 5 wrong attempts
        for ($i = 0; $i < 5; $i++) {
            $this->service->verify('+1234567890', '000000', 'register');
        }

        // Correct code should now be rejected
        $this->assertFalse($this->service->verify('+1234567890', $code, 'register'));
    }

    public function test_verify_returns_false_for_nonexistent_otp(): void
    {
        $this->assertFalse($this->service->verify('+1234567890', '123456', 'register'));
    }

    public function test_verify_does_not_cross_types(): void
    {
        $code = $this->service->generate('+1234567890', 'register');
        $this->assertFalse($this->service->verify('+1234567890', $code, 'login'));
    }

    public function test_can_request_returns_true_initially(): void
    {
        $this->assertTrue($this->service->canRequest('+1234567890'));
    }

    public function test_can_request_returns_false_after_3_requests(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->service->generate('+1234567890', 'register');
        }

        $this->assertFalse($this->service->canRequest('+1234567890'));
    }

    public function test_can_request_does_not_count_old_requests(): void
    {
        // Simulate counter expired (cache cleared = new hour window)
        Cache::forget('otp_requests:+1234567890');

        $this->assertTrue($this->service->canRequest('+1234567890'));
    }

    public function test_purge_expired_removes_expired_otps(): void
    {
        PhoneOtp::create([
            'phone'      => '+1234567890',
            'code'       => '123456',
            'type'       => 'register',
            'expires_at' => now()->subMinute(),
        ]);

        PhoneOtp::create([
            'phone'      => '+9876543210',
            'code'       => '654321',
            'type'       => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $deleted = $this->service->purgeExpired();

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('phone_otps', ['phone' => '+1234567890']);
        $this->assertDatabaseHas('phone_otps', ['phone' => '+9876543210']);
    }
}
