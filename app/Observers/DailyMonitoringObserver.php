<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\DailyMonitoring;

class DailyMonitoringObserver
{
    public function created(DailyMonitoring $monitoring): void
    {
        NotifyFarmActivity::dispatch($monitoring);
    }
}
