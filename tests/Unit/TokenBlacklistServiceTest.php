<?php

namespace Tests\Unit;

use App\Models\TokenBlacklist;
use App\Services\TokenBlacklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBlacklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private TokenBlacklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenBlacklistService;
    }

    public function test_blacklist_stores_token(): void
    {
        $this->service->blacklist('test.token.here', time() + 3600);
        $this->assertDatabaseHas('token_blacklist', ['token' => 'test.token.here']);
    }

    public function test_is_blacklisted_returns_true_for_blacklisted_token(): void
    {
        $this->service->blacklist('test.token.here', time() + 3600);
        $this->assertTrue($this->service->isBlacklisted('test.token.here'));
    }

    public function test_is_blacklisted_returns_false_for_unknown_token(): void
    {
        $this->assertFalse($this->service->isBlacklisted('unknown.token.here'));
    }

    public function test_purge_expired_removes_expired_tokens(): void
    {
        // Expired token
        TokenBlacklist::create([
            'token' => 'expired.token',
            'expires_at' => now()->subHour(),
        ]);

        // Valid token
        TokenBlacklist::create([
            'token' => 'valid.token',
            'expires_at' => now()->addHour(),
        ]);

        $deleted = $this->service->purgeExpired();

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('token_blacklist', ['token' => 'expired.token']);
        $this->assertDatabaseHas('token_blacklist', ['token' => 'valid.token']);
    }

    public function test_purge_expired_returns_zero_when_nothing_to_purge(): void
    {
        $deleted = $this->service->purgeExpired();
        $this->assertEquals(0, $deleted);
    }
}
