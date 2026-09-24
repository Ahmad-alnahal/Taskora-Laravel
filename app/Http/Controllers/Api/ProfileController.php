<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAvatarRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\VerifyCurrentPasswordRequest;
use App\Http\Requests\Profile\VerifyPasswordChangeOtpRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Services\PasswordChangeOtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(private readonly PasswordChangeOtpService $passwordChangeOtp)
    {
        //
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $user->loadCount('projects');
        $user->total_hours = (float) $user->tasks()->sum('tasks.actual_hours');

        // Each task earns at its own project's rate override, falling back to
        // the user's default rate — not one rate multiplied across every hour.
        $user->total_earnings = (float) $user->tasks()
            ->selectRaw('SUM(tasks.actual_hours * COALESCE(projects.hourly_rate, ?)) as total', [$user->hourly_rate])
            ->value('total');

        return ApiResponse::success(new ProfileResource($user));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return ApiResponse::success(new UserResource($user), 'تم تحديث البيانات');
    }

    public function verifyCurrentPassword(VerifyCurrentPasswordRequest $request)
    {
        $this->passwordChangeOtp->issue($request->user());

        return ApiResponse::success(message: 'تم إرسال رمز التأكيد إلى بريدك');
    }

    public function verifyPasswordChangeOtp(VerifyPasswordChangeOtpRequest $request)
    {
        $token = $this->passwordChangeOtp->verify($request->user(), $request->validated('code'));

        if (! $token) {
            Log::warning('profile.password_change_otp_failed', ['user_id' => $request->user()->id]);

            return ApiResponse::error('الرمز غير صحيح أو منتهي الصلاحية', 422);
        }

        return ApiResponse::success(['password_change_token' => $token]);
    }

    public function resendPasswordChangeOtp(Request $request)
    {
        $user = $request->user();

        if (! $this->passwordChangeOtp->hasActiveCode($user)) {
            return ApiResponse::error('ابدأ عملية تغيير كلمة المرور من جديد (تأكيد كلمة المرور الحالية أولاً)', 409);
        }

        $this->passwordChangeOtp->issue($user);

        return ApiResponse::success(message: 'تم إرسال رمز جديد إلى بريدك');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();
        $invalidTokenResponse = fn () => ApiResponse::error('انتهت صلاحية الجلسة، ابدأ عملية تغيير كلمة المرور من جديد', 422);

        if (! $this->passwordChangeOtp->resolveToken($request->validated('password_change_token'), $user->id)) {
            return $invalidTokenResponse();
        }

        $currentTokenId = $request->user()->currentAccessToken()->id;

        DB::transaction(function () use ($user, $request, $currentTokenId) {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        });

        Log::notice('profile.password_changed', ['user_id' => $user->id]);

        return ApiResponse::success(message: 'تم تغيير كلمة المرور بنجاح');
    }

    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return ApiResponse::success([
            'avatar_url' => Storage::disk('public')->url($path),
        ], 'تم تحديث الصورة الشخصية');
    }
}
