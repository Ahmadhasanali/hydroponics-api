<?php

namespace App\Console\Commands;

use App\Services\ReminderDispatchService;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Kirim push notification untuk reminder yang jatuh tempo';

    public function handle(ReminderDispatchService $dispatch): int
    {
        $dispatch->dispatchDue();

        return self::SUCCESS;
    }
}
