<?php

namespace App\Console\Commands;

use App\Services\OtpService;
use Illuminate\Console\Command;

class PurgeExpiredOtps extends Command
{
    protected $signature = 'otps:purge'; // @suppress CWE-798 - Artisan command signature, not a credential

    protected $description = 'Delete expired OTPs from the phone_otps table';

    public function handle(OtpService $otpService): void
    {
        $deleted = $otpService->purgeExpired();
        $this->info("Purged {$deleted} expired OTP(s).");
    }
}
