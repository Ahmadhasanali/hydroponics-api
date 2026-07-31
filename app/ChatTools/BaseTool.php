<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class BaseTool implements ChatToolContract
{
    /**
     * @return Collection<int, Farm> Farm yang user-nya terdaftar sebagai member
     */
    protected function accessibleFarms(User $user): Collection
    {
        return $user->farms()->withCount('tanks')->get();
    }

    /**
     * Cari tank yang farm-nya berisi user tersebut; null jika tidak berhak.
     */
    protected function accessibleTank(int $tankId, User $user): ?Tank
    {
        return Tank::whereKey($tankId)
            ->whereHas('farm', fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->first();
    }

    protected function tankPayload(Tank $tank): array
    {
        return [
            'id' => $tank->id,
            'farm_id' => $tank->farm_id,
            'farm_name' => $tank->farm?->name,
            'name' => $tank->name,
            'capacity_liter' => $tank->capacity_liter,
            'is_active' => $tank->is_active,
            'target_ppm_min' => $tank->target_ppm_min,
            'target_ppm_max' => $tank->target_ppm_max,
            'target_ph_min' => $tank->target_ph_min,
            'target_ph_max' => $tank->target_ph_max,
            'current_ppm' => $tank->current_ppm,
            'current_ph' => $tank->current_ph,
            'current_water_temperature' => $tank->current_water_temperature,
            'last_condition_updated_at' => $tank->last_condition_updated_at?->toIso8601String(),
        ];
    }
}
