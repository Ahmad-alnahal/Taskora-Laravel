<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/verify-reset-code', [AuthController::class, 'verifyResetCode'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::get('/tasks/export/download', [TaskController::class, 'downloadExport'])
        ->name('tasks.export.download')
        ->middleware('signed');

    // Authenticated but not necessarily OTP-verified yet — a freshly issued
    // token must be able to reach these three even before it's confirmed.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-others', [AuthController::class, 'logoutOthers']);
        Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
        Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:3,1');

        // Everything else requires the current token to have passed OTP.
        Route::middleware('token.verified')->group(function () {
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::post('/profile/password/verify-current', [ProfileController::class, 'verifyCurrentPassword'])->middleware('throttle:5,1');
            Route::post('/profile/password/verify-otp', [ProfileController::class, 'verifyPasswordChangeOtp'])->middleware('throttle:10,1');
            Route::post('/profile/password/resend-otp', [ProfileController::class, 'resendPasswordChangeOtp'])->middleware('throttle:3,1');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:10,1');
            Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);

            Route::get('/dashboard', [DashboardController::class, 'index']);

            // Writes throttled (60/min/user) — generous enough for offline sync
            // queue replay, tight enough to block scripted abuse.
            Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store')->middleware('throttle:60,1');
            Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
            Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update')->middleware('throttle:60,1');
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy')->middleware('throttle:60,1');

            Route::get('/projects/{project}/tasks', [TaskController::class, 'index']);
            Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->middleware('throttle:60,1');

            Route::post('/tasks/export', [TaskController::class, 'export']);
            Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
            Route::put('/tasks/{task}', [TaskController::class, 'update'])->middleware('throttle:60,1');
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->middleware('throttle:60,1');
        });
    });
});
