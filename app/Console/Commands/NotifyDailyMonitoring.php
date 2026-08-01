<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyDailyMonitoring extends Command
{
    protected $signature = 'notify:daily-monitoring';

    protected $description = 'Mengirim pengingat monitoring harian ke pengguna dengan perangkat terdaftar';

    public function handle(PushNotificationService $push): int
    {
        $cacheKey = 'daily_monitoring_reminder:'.now()->toDateString();

        if (Cache::has($cacheKey)) {
            $this->info('Pengingat sudah terkirim hari ini.');

            return self::SUCCESS;
        }

        $recipients = User::whereHas('pushSubscriptions')->get();

        foreach ($recipients as $user) {
            $push->sendToUser(
                $user,
                'Waktunya Monitoring',
                'Catat PPM & pH tangki hari ini',
                route('daily-monitoring.create'),
            );
        }

        Cache::put($cacheKey, true, now()->endOfDay());
        $this->info("Pengingat dikirim ke {$recipients->count()} pengguna.");

        return self::SUCCESS;
    }
}
