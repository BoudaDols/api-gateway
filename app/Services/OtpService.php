<?php

namespace App\Services;

use App\Models\PhoneOtp;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    private const OTP_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const MAX_REQUESTS_HOUR = 3;

    /**
     * Generate a new OTP for the given phone and type.
     * Deletes any previous unused OTP for the same phone+type first.
     */
    public function generate(string $phone, string $type): string
    {
        // Remove previous unused OTPs for this phone+type
        PhoneOtp::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneOtp::create([
            'phone' => $phone,
            'code' => $code,
            'type' => $type,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        // Increment request counter in cache (persists across generate/delete cycle)
        $key = "otp_requests:{$phone}";
        Cache::add($key, 0, now()->addHour());
        Cache::increment($key);

        return $code;
    }

    /**
     * Verify an OTP for the given phone and type.
     * Returns true on success, false on any failure.
     */
    public function verify(string $phone, string $code, string $type): bool
    {
        $otp = PhoneOtp::where('phone', $phone)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $otp) {
            return false;
        }

        // Check expiry
        if ($otp->expires_at->isPast()) {
            return false;
        }

        // Check max attempts
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        // Increment attempts before checking code (prevents timing enumeration)
        $otp->increment('attempts');

        if ($otp->code !== $code) {
            return false;
        }

        // Mark as verified
        $otp->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Check if the phone can request a new OTP.
     * Returns false if 3 or more OTPs were requested in the last hour.
     */
    public function canRequest(string $phone): bool
    {
        $count = Cache::get("otp_requests:{$phone}", 0);

        return $count < self::MAX_REQUESTS_HOUR;
    }

    /**
     * Delete expired OTPs. Called by the scheduled artisan command.
     */
    public function purgeExpired(): int
    {
        return PhoneOtp::where('expires_at', '<', now())->delete();
    }
}
