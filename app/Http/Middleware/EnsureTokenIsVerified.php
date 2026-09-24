<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token || $token->verified_at === null) {
            return ApiResponse::error(
                'يجب تأكيد هذا الجهاز برمز التحقق (OTP) المرسل إلى بريدك أولاً',
                403,
                errorCode: 'otp_required',
            );
        }

        return $next($request);
    }
}
