<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\PhDownLog;

class PhDownLogObserver
{
    public function created(PhDownLog $log): void
    {
        NotifyFarmActivity::dispatch($log);
    }
}
