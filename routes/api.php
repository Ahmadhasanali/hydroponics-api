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
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffMonitoringController;
use App\Http\Controllers\Staff\StaffNutrientAdditionController;
use App\Http\Controllers\Staff\StaffPhDownController;
use App\Http\Controllers\Staff\StaffReminderController;
use App\Http\Controllers\Staff\StaffReportController;
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
    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        // Auth
        Route::post('logout', [AuthController::class, 'logout']);
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
        Route::apiResource('monitoring', DailyMonitoringController::class)
            ->parameters(['monitoring' => 'dailyMonitoring']);

        // Nutrient Addition
        Route::apiResource('nutrients', NutrientAdditionController::class)
            ->parameters(['nutrients' => 'nutrientAddition']);

        // pH Down
        Route::apiResource('ph-down', PhDownLogController::class)
            ->parameters(['ph-down' => 'phDownLog']);

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
        Route::post('reminders/{reminder}/occurrences/{occurrence}/done', [ReminderController::class, 'occurrenceDone']);
        Route::post('reminders/{reminder}/occurrences/{occurrence}/skip', [ReminderController::class, 'occurrenceSkip']);
    });

    // Staff (authenticated)
    Route::middleware(['auth:sanctum', 'staff'])->group(function () {
        Route::post('staff/logout', [StaffAuthController::class, 'logout']);
        Route::get('staff/dashboard', [StaffDashboardController::class, 'index']);
        Route::get('staff/monitoring', [StaffMonitoringController::class, 'index']);
        Route::post('staff/monitoring', [StaffMonitoringController::class, 'store']);
        Route::get('staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'show']);
        Route::patch('staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'update']);
        Route::delete('staff/monitoring/{dailyMonitoring}', [StaffMonitoringController::class, 'destroy']);

        // Nutrient Addition
        Route::get('staff/nutrients', [StaffNutrientAdditionController::class, 'index']);
        Route::post('staff/nutrients', [StaffNutrientAdditionController::class, 'store']);
        Route::get('staff/nutrients/{nutrientAddition}', [StaffNutrientAdditionController::class, 'show']);
        Route::patch('staff/nutrients/{nutrientAddition}', [StaffNutrientAdditionController::class, 'update']);
        Route::delete('staff/nutrients/{nutrientAddition}', [StaffNutrientAdditionController::class, 'destroy']);

        // pH Down
        Route::get('staff/ph-down', [StaffPhDownController::class, 'index']);
        Route::post('staff/ph-down', [StaffPhDownController::class, 'store']);
        Route::get('staff/ph-down/{phDownLog}', [StaffPhDownController::class, 'show']);
        Route::patch('staff/ph-down/{phDownLog}', [StaffPhDownController::class, 'update']);
        Route::delete('staff/ph-down/{phDownLog}', [StaffPhDownController::class, 'destroy']);

        // Reminders
        Route::get('staff/reminders', [StaffReminderController::class, 'index']);
        Route::post('staff/reminders', [StaffReminderController::class, 'store']);
        Route::delete('staff/reminders/{reminder}', [StaffReminderController::class, 'destroy']);
        Route::get('staff/reminders/calendar', [StaffReminderController::class, 'calendar']);
        Route::post('staff/reminders/occurrences/{occurrence}/done', [StaffReminderController::class, 'occurrenceDone']);
        Route::post('staff/reminders/occurrences/{occurrence}/skip', [StaffReminderController::class, 'occurrenceSkip']);

        // Reports
        Route::get('staff/reports/monitoring', [StaffReportController::class, 'monitoring']);
        Route::get('staff/reports/nutrients', [StaffReportController::class, 'nutrient']);
        Route::get('staff/reports/ph-down', [StaffReportController::class, 'phDown']);
    });
});
