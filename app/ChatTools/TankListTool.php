<?php

namespace App\ChatTools;

use App\Models\Farm\Tank;
use App\Models\User;

class TankListTool extends BaseTool
{
    public function name(): string
    {
        return 'get_tanks';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar tank milik pengguna, opsional difilter berdasarkan farm_id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm untuk memfilter tank.'],
            ],
            'required' => [],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if (isset($args['farm_id'])) {
            if (! $farms->contains('id', (int) $args['farm_id'])) {
                return ['error' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }

            $farms = $farms->where('id', (int) $args['farm_id']);
        }

        $tanks = Tank::query()
            ->with('farm:id,name')
            ->whereIn('farm_id', $farms->pluck('id'))
            ->orderBy('id')
            ->get();

        return ['data' => $tanks->map(fn (Tank $tank): array => $this->tankPayload($tank))->all()];
    }
}
