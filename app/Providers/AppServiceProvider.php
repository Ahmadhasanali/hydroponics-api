<?php

namespace App\Providers;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Observers\ActivityLogObserver;
use App\Observers\DailyMonitoringObserver;
use App\Observers\NutrientAdditionObserver;
use App\Observers\PhDownLogObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Messaging::class, function () {
            $serviceAccount = config('fcm.service_account_json');

            if (! $serviceAccount || ! is_file($serviceAccount)) {
                Log::warning('FCM belum dikonfigurasi: isi FCM_SERVICE_ACCOUNT_JSON di .env.');

                return null;
            }

            return (new Factory)->withServiceAccount($serviceAccount)->createMessaging();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Farm::observe(ActivityLogObserver::class);
        Tank::observe(ActivityLogObserver::class);
        DailyMonitoring::observe(ActivityLogObserver::class);
        NutrientAddition::observe(ActivityLogObserver::class);
        PhDownLog::observe(ActivityLogObserver::class);
        DailyMonitoring::observe(DailyMonitoringObserver::class);
        NutrientAddition::observe(NutrientAdditionObserver::class);
        PhDownLog::observe(PhDownLogObserver::class);

        $this->loadMigrationsFrom(
            [
                database_path('migrations/User'),
                database_path('migrations'),
            ],
        );
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->input('username').'|'.$request->ip()),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });
    }
}
