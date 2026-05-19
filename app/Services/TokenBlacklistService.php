<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TokenBlacklistService
{
    private const PREFIX = 'token_blacklist:';

    /**
     * Blacklist a token. It auto-expires when the token itself would expire.
     */
    public function blacklist(string $token, int $expiresAt): void
    {
        $ttl = max($expiresAt - time(), 0);

        Cache::put(self::PREFIX . hash('sha256', $token), true, $ttl);
    }

    /**
     * Check if a token is blacklisted.
     */
    public function isBlacklisted(string $token): bool
    {
        return Cache::has(self::PREFIX . hash('sha256', $token));
    }

    /**
     * No-op — Redis TTL handles expiration automatically.
     */
    public function purgeExpired(): int
    {
        return 0;
    }
}
