<?php

namespace App\Services;

use App\Mail\PasswordChangeOtpMail;
use App\Models\PasswordChangeCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Independent of TokenOtpService (device/session confirmation) by design —
 * separate table, separate purpose. This one guards the "already logged in,
 * want to change my password" flow, mirroring the anonymous forgot-password
 * flow's code + short-lived Crypt token pattern.
 */
class PasswordChangeOtpService
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const CHANGE_TOKEN_TTL_MINUTES = 15;

    public function issue(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        PasswordChangeCode::where('user_id', $user->id)->delete();

        PasswordChangeCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'created_at' => now(),
        ]);

        Mail::to($user->email)->queue(new PasswordChangeOtpMail($code));
    }

    public function hasActiveCode(User $user): bool
    {
        return PasswordChangeCode::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function verify(User $user, string $code): ?string
    {
        $record = PasswordChangeCode::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return null;
        }

        $record->delete();

        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'expires' => now()->addMinutes(self::CHANGE_TOKEN_TTL_MINUTES)->timestamp,
        ]));
    }

    /**
     * Returns the user_id bound to the token, or null if it is invalid,
     * expired, or was issued to a different user than the caller.
     */
    public function resolveToken(string $token, int $expectedUserId): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Exception) {
            return null;
        }

        if (! isset($payload['user_id'], $payload['expires']) || $payload['expires'] < now()->timestamp) {
            return null;
        }

        if ((int) $payload['user_id'] !== $expectedUserId) {
            return null;
        }

        return (int) $payload['user_id'];
    }
}
