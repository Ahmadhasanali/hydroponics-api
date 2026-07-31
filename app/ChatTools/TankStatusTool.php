<?php

namespace App\ChatTools;

use App\Models\User;

class TankStatusTool extends BaseTool
{
    public function name(): string
    {
        return 'get_tank_status';
    }

    public function description(): string
    {
        return 'Mendapatkan kondisi terkini satu tank: PPM, pH, suhu air, dan rentang target.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'tank_id' => ['type' => 'INTEGER', 'description' => 'ID tank yang ingin dicek kondisinya.'],
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

        $tank->load('farm:id,name');

        return ['data' => $this->tankPayload($tank)];
    }
}
