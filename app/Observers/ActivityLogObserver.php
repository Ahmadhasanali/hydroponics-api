<?php

namespace App\Observers;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;

class ActivityLogObserver
{
    public function created(Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): void
    {
        $this->record('created', $entity);
    }

    public function updated(Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): void
    {
        $this->record('updated', $entity);
    }

    public function deleted(Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): void
    {
        $this->record('deleted', $entity);
    }

    private function record(string $action, Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): void
    {
        if ($entity instanceof Farm) {
            $farmId = $entity->id;
        } elseif ($entity instanceof Tank) {
            $farmId = $entity->farm_id;
        } else {
            $farmId = $entity->tank?->farm_id;
        }

        if (! $farmId || ! auth()->id()) {
            return;
        }

        $entityType = class_basename($entity);
        $entityType = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($entityType)));

        $name = match (true) {
            $entity instanceof Farm, $entity instanceof Tank => $entity->name,
            default => "{$entityType} #{$entity->id}",
        };

        ActivityLog::create([
            'farm_id' => $farmId,
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entity->id,
            'description' => ucfirst("{$action} {$entityType}: {$name}"),
            'created_at' => now(),
        ]);
    }
}
