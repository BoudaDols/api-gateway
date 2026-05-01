<?php

namespace Tests\Unit;

use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    private SmsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmsService;
    }

    public function test_log_driver_writes_to_log(): void
    {
        config(['sms.driver' => 'log']);

        // Use shouldReceive instead of spy to avoid breaking Log::channel
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, '[SMS]'));

        $this->service->send('+1234567890', 'Your code is: 123456');
    }

    public function test_log_driver_includes_phone_in_message(): void
    {
        config(['sms.driver' => 'log']);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, '+1234567890'));

        $this->service->send('+1234567890', 'Your code is: 123456');
    }

    public function test_unsupported_driver_throws_exception(): void
    {
        config(['sms.driver' => 'unsupported']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported SMS driver');

        $this->service->send('+1234567890', 'Test message');
    }

    public function test_twilio_driver_throws_without_credentials(): void
    {
        config(['sms.driver' => 'twilio', 'sms.twilio.sid' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Twilio credentials not configured');

        $this->service->send('+1234567890', 'Test message');
    }

    public function test_vonage_driver_throws_without_credentials(): void
    {
        config(['sms.driver' => 'vonage', 'sms.vonage.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vonage credentials not configured');

        $this->service->send('+1234567890', 'Test message');
    }

    public function test_aws_sns_driver_throws_without_credentials(): void
    {
        config(['sms.driver' => 'aws_sns', 'sms.aws_sns.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AWS credentials not configured');

        $this->service->send('+1234567890', 'Test message');
    }
}
