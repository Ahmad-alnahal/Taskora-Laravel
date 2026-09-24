<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class TokenOtpService
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /**
     * Generate a fresh OTP for this token, store it hashed, and email the
     * plain code. Every credential-based token (register/login/resend)
     * starts unverified — the token exists and can be stored by the client
     * immediately, but every other protected endpoint rejects it until this
     * code is confirmed via verify().
     */
    public function issue(PersonalAccessToken $token, string $email): void
    {
        $code = (string) random_int(100000, 999999);

        $token->forceFill([
            'verified_at' => null,
            'otp_code_hash' => Hash::make($code),
            'otp_attempts' => 0,
            'otp_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        Mail::to($email)->queue(new OtpCodeMail($code));
    }

    public function verify(PersonalAccessToken $token, string $code): bool
    {
        if (! $this->hasActiveCode($token)) {
            return false;
        }

        if (! Hash::check($code, $token->otp_code_hash)) {
            $token->increment('otp_attempts');

            return false;
        }

        $token->forceFill([
            'verified_at' => now(),
            'otp_code_hash' => null,
            'otp_attempts' => 0,
            'otp_expires_at' => null,
        ])->save();

        return true;
    }

    private function hasActiveCode(PersonalAccessToken $token): bool
    {
        return $token->otp_code_hash !== null
            && $token->otp_expires_at !== null
            && $token->otp_expires_at->isFuture()
            && $token->otp_attempts < self::MAX_ATTEMPTS;
    }
}
