<?php

namespace App\Observers;

use App\Jobs\NotifyFarmActivity;
use App\Models\Farm\NutrientAddition;

class NutrientAdditionObserver
{
    public function created(NutrientAddition $addition): void
    {
        NotifyFarmActivity::dispatch($addition);
    }
}
