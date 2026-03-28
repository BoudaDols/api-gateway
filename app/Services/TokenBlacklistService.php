<?php

namespace App\Services;

use App\Models\TokenBlacklist;
use Carbon\Carbon;

class TokenBlacklistService
{
    public function blacklist(string $token, int $expiresAt): void
    {
        TokenBlacklist::create([
            'token' => $token,
            'expires_at' => Carbon::createFromTimestamp($expiresAt),
        ]);
    }

    public function isBlacklisted(string $token): bool
    {
        return TokenBlacklist::where('token', $token)->exists();
    }

    public function purgeExpired(): int
    {
        return TokenBlacklist::where('expires_at', '<', now())->delete();
    }
}
