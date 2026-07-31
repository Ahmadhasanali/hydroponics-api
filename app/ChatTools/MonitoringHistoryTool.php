<?php

namespace App\ChatTools;

use App\Models\Farm\DailyMonitoring;
use App\Models\User;

class MonitoringHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_monitoring_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat monitoring harian (PPM, pH, suhu) satu tank dalam jumlah hari terakhir.';
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

        $records = $tank->dailyMonitorings()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (DailyMonitoring $monitoring): array => [
                'log_date' => $monitoring->log_date->toDateString(),
                'ppm' => $monitoring->ppm,
                'ph' => $monitoring->ph,
                'water_temperature' => $monitoring->water_temperature,
                'notes' => $monitoring->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
