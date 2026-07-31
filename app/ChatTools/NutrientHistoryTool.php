<?php

namespace App\ChatTools;

use App\Models\Farm\NutrientAddition;
use App\Models\User;

class NutrientHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_nutrient_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat penambahan nutrisi AB Mix (PPM sebelum/sesudah, ml A/B) satu tank dalam jumlah hari terakhir.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank.'],
                'days' => ['type' => 'INTEGER', 'description' => 'Jumlah hari ke belakang (1-90, default 7).'],
            ],
            'required' => ['tank_id'],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $tank = $this->accessibleTank((int) ($args['tank_id'] ?? 0), $user);

        if ($tank === null) {
            return ['error' => 'Tank tidak ditemukan atau Anda tidak memiliki akses.'];
        }

        $days = max(1, min(90, (int) ($args['days'] ?? 7)));

        $records = $tank->nutrientAdditions()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (NutrientAddition $addition): array => [
                'log_date' => $addition->log_date->toDateString(),
                'ppm_before' => $addition->ppm_before,
                'ppm_after' => $addition->ppm_after,
                'nutrient_a_ml' => $addition->nutrient_a_ml,
                'nutrient_b_ml' => $addition->nutrient_b_ml,
                'notes' => $addition->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
