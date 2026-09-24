<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\TokenOtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly TokenOtpService $otp)
    {
        //
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'username' => $this->generateUniqueUsername($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'hourly_rate' => $data['hourly_rate'],
        ]);

        $newToken = $user->createToken($data['device_name'] ?? 'mobile');
        $this->otp->issue($newToken->accessToken, $user->email);

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $newToken->plainTextToken,
            'otp_required' => true,
        ], 'تم إنشاء الحساب بنجاح، تحقق من بريدك لتأكيد الجهاز', 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::warning('auth.login_failed', ['email' => $data['email']]);

            return ApiResponse::error('البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
        }

        $newToken = $user->createToken($data['device_name'] ?? 'mobile');
        $this->otp->issue($newToken->accessToken, $user->email);

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $newToken->plainTextToken,
            'otp_required' => true,
        ], 'تم تسجيل الدخول، تحقق من بريدك لتأكيد الجهاز');
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $token = $request->user()->currentAccessToken();

        if (! $this->otp->verify($token, $request->validated('code'))) {
            Log::warning('auth.device_otp_failed', ['user_id' => $request->user()->id]);

            return ApiResponse::error('الرمز غير صحيح أو منتهي الصلاحية', 422);
        }

        return ApiResponse::success(new UserResource($request->user()), 'تم تأكيد الجهاز بنجاح');
    }

    public function resendOtp(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token->verified_at !== null) {
            return ApiResponse::error('هذا الجهاز مؤكَّد بالفعل', 409);
        }

        $this->otp->issue($token, $request->user()->email);

        return ApiResponse::success(message: 'تم إرسال رمز جديد إلى بريدك');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(message: 'تم تسجيل الخروج');
    }

    public function logoutOthers(Request $request)
    {
        $request->user()->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        Log::notice('auth.logout_others', ['user_id' => $request->user()->id]);

        return ApiResponse::success(message: 'تم تسجيل الخروج من كل الأجهزة الأخرى');
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            PasswordResetCode::where('email', $email)->delete();

            PasswordResetCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
            ]);

            Mail::to($email)->queue(new PasswordResetCodeMail($code));
        }

        return ApiResponse::success(message: 'إذا كان البريد مسجلاً لدينا، تم إرسال رمز التحقق إليه');
    }

    public function verifyResetCode(VerifyResetCodeRequest $request)
    {
        $data = $request->validated();

        $reset = PasswordResetCode::where('email', $data['email'])
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        $invalidCodeResponse = fn () => ApiResponse::error('الرمز غير صحيح أو منتهي الصلاحية', 422);

        if (! $reset || $reset->attempts >= 5) {
            return $invalidCodeResponse();
        }

        if (! Hash::check($data['code'], $reset->code_hash)) {
            $reset->increment('attempts');

            Log::warning('auth.password_reset_code_failed', ['email' => $data['email']]);

            return $invalidCodeResponse();
        }

        $reset->delete();

        $resetToken = Crypt::encryptString(json_encode([
            'email' => $data['email'],
            'expires' => now()->addMinutes(5)->timestamp,
        ]));

        return ApiResponse::success(['reset_token' => $resetToken], 'تم التحقق من الرمز');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $invalidTokenResponse = fn () => ApiResponse::error('انتهت صلاحية الجلسة، ابدأ عملية إعادة التعيين من جديد', 422);

        try {
            $payload = json_decode(Crypt::decryptString($request->validated('reset_token')), true);
        } catch (\Exception) {
            return $invalidTokenResponse();
        }

        if (! isset($payload['email'], $payload['expires']) || $payload['expires'] < now()->timestamp) {
            return $invalidTokenResponse();
        }

        $user = User::where('email', $payload['email'])->first();

        if (! $user) {
            return $invalidTokenResponse();
        }

        DB::transaction(function () use ($user, $request) {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->delete();
        });

        Log::notice('auth.password_reset', ['user_id' => $user->id]);

        return ApiResponse::success(message: 'تم تغيير كلمة المرور بنجاح');
    }

    private function generateUniqueUsername(string $name): string
    {
        $base = Str::slug($name, '_', 'ar') ?: 'user';
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = "{$base}_{$suffix}";
            $suffix++;
        }

        return $username;
    }
}
