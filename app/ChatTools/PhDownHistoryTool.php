<?php

namespace App\ChatTools;

use App\Models\Farm\PhDownLog;
use App\Models\User;

class PhDownHistoryTool extends BaseTool
{
    public function name(): string
    {
        return 'get_ph_down_history';
    }

    public function description(): string
    {
        return 'Mendapatkan riwayat penggunaan pH Down (pH sebelum/sesudah, ml) satu tank dalam jumlah hari terakhir.';
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

        $records = $tank->phDownLogs()
            ->where('log_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('log_date')
            ->limit(50)
            ->get()
            ->map(fn (PhDownLog $log): array => [
                'log_date' => $log->log_date->toDateString(),
                'ph_before' => $log->ph_before,
                'ph_after' => $log->ph_after,
                'ph_down_ml' => $log->ph_down_ml,
                'notes' => $log->notes,
            ])
            ->all();

        return ['data' => $records];
    }
}
