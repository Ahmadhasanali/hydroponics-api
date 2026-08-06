<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatSessionController;
use App\Http\Controllers\DailyMonitoringController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\Farm\FarmController;
use App\Http\Controllers\Farm\FarmStaffController;
use App\Http\Controllers\FarmUserController;
use App\Http\Controllers\NutrientAdditionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PhDownLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Staff\StaffAuthController;
use App\Http\Controllers\TankController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth (no auth required)
    Route::post('register', [RegistrationController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('password/forgot', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:6,1');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');

    Route::post('staff/login', [StaffAuthController::class, 'login'])
        ->middleware('throttle:staff-login');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('staff/logout', [StaffAuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('user', [ProfileController::class, 'update']);
        Route::post('email/resend-verification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1');

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Farms
        Route::apiResource('farms', FarmController::class);
        Route::post('farms/{farm}/transfer', [FarmController::class, 'transferOwnership']);
        Route::get('farms/{farm}/members', [FarmUserController::class, 'index']);
        Route::post('farms/{farm}/members', [FarmUserController::class, 'store']);
        Route::delete('farms/{farm}/members/{farmUser}', [FarmUserController::class, 'destroy']);
        Route::post('farms/{farm}/staff', [FarmStaffController::class, 'store']);
        Route::put('farms/{farm}/staff/{staff}/password', [FarmStaffController::class, 'resetPassword']);
        Route::put('farms/{farm}/staff/{staff}/toggle', [FarmStaffController::class, 'toggle']);
        Route::delete('farms/{farm}/staff/{staff}', [FarmStaffController::class, 'destroy']);

        // Tanks
        Route::apiResource('tanks', TankController::class);

        // Daily Monitoring
        Route::apiResource('monitoring', DailyMonitoringController::class);

        // Nutrient Addition
        Route::apiResource('nutrients', NutrientAdditionController::class);

        // pH Down
        Route::apiResource('ph-down', PhDownLogController::class);

        // Reports
        Route::get('reports/monitoring', [ReportController::class, 'monitoring']);
        Route::get('reports/nutrients', [ReportController::class, 'nutrient']);
        Route::get('reports/ph-down', [ReportController::class, 'phDown']);

        // Chat
        Route::post('chat', ChatController::class)->middleware('throttle:chat');
        Route::get('chat/sessions', [ChatSessionController::class, 'index']);
        Route::post('chat/sessions', [ChatSessionController::class, 'store']);
        Route::post('chat/sessions/migrate', [ChatSessionController::class, 'migrate']);
        Route::patch('chat/sessions/{session}', [ChatSessionController::class, 'update']);
        Route::delete('chat/sessions/{session}', [ChatSessionController::class, 'destroy']);
        Route::get('chat/sessions/{session}/messages', [ChatSessionController::class, 'messages']);
        Route::delete('chat/sessions/{session}/messages', [ChatSessionController::class, 'clear']);

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // Push Subscriptions (FCM)
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);

        // Reminders
        Route::apiResource('reminders', ReminderController::class);
    });
});
