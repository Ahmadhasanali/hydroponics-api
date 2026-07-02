<?php

namespace App\Providers;

use App\Models\Farm;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Observers\ActivityLogObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
