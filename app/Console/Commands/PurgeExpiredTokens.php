<?php

namespace App\Console\Commands;

use App\Services\TokenBlacklistService;
use Illuminate\Console\Command;

class PurgeExpiredTokens extends Command
{
    protected $signature = 'tokens:purge'; // @suppress CWE-798 - Artisan command signature, not a credential

    protected $description = 'Delete expired tokens from the blacklist';

    public function handle(TokenBlacklistService $blacklistService): void
    {
        $deleted = $blacklistService->purgeExpired();
        $this->info("Purged {$deleted} expired token(s) from blacklist.");
    }
}
