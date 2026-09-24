<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    public function isOtpVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
