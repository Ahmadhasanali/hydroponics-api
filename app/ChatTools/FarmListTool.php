<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\User;

class FarmListTool extends BaseTool
{
    public function name(): string
    {
        return 'get_farms';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar farm beserta jumlah tank milik pengguna yang sedang login.';
    }

    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => [], 'required' => []];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user)->map(fn (Farm $farm): array => [
            'id' => $farm->id,
            'name' => $farm->name,
            'address' => $farm->address,
            'tank_count' => $farm->tanks_count,
        ])->all();

        return ['data' => $farms];
    }
}
