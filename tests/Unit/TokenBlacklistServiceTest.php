<?php

namespace Tests\Unit;

use App\Services\TokenBlacklistService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TokenBlacklistServiceTest extends TestCase
{
    private TokenBlacklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new TokenBlacklistService;
    }

    public function test_blacklist_stores_token_in_cache(): void
    {
        $this->service->blacklist('test.token.here', time() + 3600);
        $this->assertTrue($this->service->isBlacklisted('test.token.here'));
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

    public function test_purge_expired_returns_zero(): void
    {
        // Redis TTL handles expiration — purgeExpired is a no-op
        $deleted = $this->service->purgeExpired();
        $this->assertEquals(0, $deleted);
    }
}
