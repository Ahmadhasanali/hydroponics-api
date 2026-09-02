<?php

use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUser;
use App\Models\MessagingLinkCode;
use App\Models\TelegramPendingTransaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'staff' => EnsureStaff::class,
            'user' => EnsureUser::class,
        ]);
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('push-subscriptions')
                || $request->is('staff/push-subscriptions'),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('chat:purge-deleted-sessions')->hourly();

        // Pengingat monitoring harian hanya aktif jika DAILY_REMINDER_HOUR diisi
        // (contoh: 08:00). Kosongkan/nonaktifkan di .env untuk mematikannya.
        if ($dailyReminderHour = config('app.daily_reminder_hour')) {
            $schedule->command('notify:daily-monitoring')->dailyAt($dailyReminderHour);
        }
        $schedule->command('app:sync-disposable-email-domains')->cron('0 0 1 */6 *');
        $schedule->command('reminders:dispatch')->everyMinute();
        $schedule->command('reminders:prune-sent')->dailyAt('03:00');
        $schedule->command('email:resend-unverified')->everyMinute();
        $schedule->call(function (): void {
            TelegramPendingTransaction::where('expires_at', '<', now())->delete();
            MessagingLinkCode::where('expires_at', '<', now()->subDay())->delete();
        })->everyFiveMinutes();
    })
    ->create();
