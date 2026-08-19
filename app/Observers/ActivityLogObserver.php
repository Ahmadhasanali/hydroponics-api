<?php

namespace App\Observers;

use App\Models\Farm;
use App\Models\Farm\ActivityLog;
use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\NutrientAddition;
use App\Models\Farm\PhDownLog;
use App\Models\Farm\Staff;
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

        $user = auth('staff')->user() ?? auth()->user();

        if ($user instanceof Staff) {
            $userId = null;
            $staffId = $user->id;
        } else {
            $userId = $user?->id;
            $staffId = null;
        }

        if (! $farmId || (! $userId && ! $staffId)) {
            return;
        }

        $entityType = class_basename($entity);
        $entityType = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($entityType)));

        $name = match (true) {
            $entity instanceof Farm, $entity instanceof Tank => $entity->name,
            default => "#{$entity->id}",
        };

        $beforeState = null;
        $afterState = null;

        if ($action === 'updated') {
            // $entity is the model AFTER save; Eloquent already fired observer
            // Use getOriginal() for pre-update values and getAttributes() for current
            $beforeState = $this->snapshotFromOriginal($entity);
            $afterState = $this->snapshot($entity);
        } elseif ($action === 'created') {
            $afterState = $this->snapshot($entity);
        } elseif ($action === 'deleted') {
            $beforeState = $this->snapshot($entity);
        }

        ActivityLog::create([
            'farm_id' => $farmId,
            'user_id' => $userId,
            'staff_id' => $staffId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entity->id,
            'description' => ucfirst("{$action} {$entityType} {$name}"),
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'created_at' => now(),
        ]);
    }

    private function snapshot(Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): array
    {
        return match (true) {
            $entity instanceof Farm => [
                'name' => $entity->name,
                'address' => $entity->address,
                'description' => $entity->description,
            ],
            $entity instanceof Tank => [
                'name' => $entity->name,
                'capacity_liter' => $entity->capacity_liter,
                'notes' => $entity->notes,
                'target_ppm_min' => $entity->target_ppm_min,
                'target_ppm_max' => $entity->target_ppm_max,
                'target_ph_min' => $entity->target_ph_min,
                'target_ph_max' => $entity->target_ph_max,
                'is_active' => $entity->is_active,
            ],
            $entity instanceof DailyMonitoring => [
                'tank_id' => $entity->tank_id,
                'log_date' => $entity->log_date,
                'ppm' => $entity->ppm,
                'ph' => $entity->ph,
                'water_temperature' => $entity->water_temperature,
                'notes' => $entity->notes,
            ],
            $entity instanceof NutrientAddition => [
                'tank_id' => $entity->tank_id,
                'log_date' => $entity->log_date,
                'ppm_before' => $entity->ppm_before,
                'ppm_after' => $entity->ppm_after,
                'nutrient_a_ml' => $entity->nutrient_a_ml,
                'nutrient_b_ml' => $entity->nutrient_b_ml,
                'notes' => $entity->notes,
            ],
            $entity instanceof PhDownLog => [
                'tank_id' => $entity->tank_id,
                'log_date' => $entity->log_date,
                'ph_before' => $entity->ph_before,
                'ph_after' => $entity->ph_after,
                'ph_down_ml' => $entity->ph_down_ml,
                'notes' => $entity->notes,
            ],
        };
    }

    private function snapshotFromOriginal(Farm|Tank|DailyMonitoring|NutrientAddition|PhDownLog $entity): array
    {
        $orig = $entity->getOriginal();

        return match (true) {
            $entity instanceof Farm => [
                'name' => $orig['name'] ?? null,
                'address' => $orig['address'] ?? null,
                'description' => $orig['description'] ?? null,
            ],
            $entity instanceof Tank => [
                'name' => $orig['name'] ?? null,
                'capacity_liter' => $orig['capacity_liter'] ?? null,
                'notes' => $orig['notes'] ?? null,
                'target_ppm_min' => $orig['target_ppm_min'] ?? null,
                'target_ppm_max' => $orig['target_ppm_max'] ?? null,
                'target_ph_min' => $orig['target_ph_min'] ?? null,
                'target_ph_max' => $orig['target_ph_max'] ?? null,
                'is_active' => $orig['is_active'] ?? null,
            ],
            $entity instanceof DailyMonitoring => [
                'tank_id' => $orig['tank_id'] ?? null,
                'log_date' => $orig['log_date'] ?? null,
                'ppm' => $orig['ppm'] ?? null,
                'ph' => $orig['ph'] ?? null,
                'water_temperature' => $orig['water_temperature'] ?? null,
                'notes' => $orig['notes'] ?? null,
            ],
            $entity instanceof NutrientAddition => [
                'tank_id' => $orig['tank_id'] ?? null,
                'log_date' => $orig['log_date'] ?? null,
                'ppm_before' => $orig['ppm_before'] ?? null,
                'ppm_after' => $orig['ppm_after'] ?? null,
                'nutrient_a_ml' => $orig['nutrient_a_ml'] ?? null,
                'nutrient_b_ml' => $orig['nutrient_b_ml'] ?? null,
                'notes' => $orig['notes'] ?? null,
            ],
            $entity instanceof PhDownLog => [
                'tank_id' => $orig['tank_id'] ?? null,
                'log_date' => $orig['log_date'] ?? null,
                'ph_before' => $orig['ph_before'] ?? null,
                'ph_after' => $orig['ph_after'] ?? null,
                'ph_down_ml' => $orig['ph_down_ml'] ?? null,
                'notes' => $orig['notes'] ?? null,
            ],
        };
    }
}
