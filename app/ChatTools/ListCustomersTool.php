<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\Customer;
use App\Models\User;

class ListCustomersTool extends BaseTool
{
    public function name(): string
    {
        return 'list_customers';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar pelanggan (warung/toko) milik farm pengguna beserta id-nya. Panggil tool ini sebelum create_sale agar bisa memakai customer_id yang benar, atau untuk mencari tahu apakah pelanggan sudah terdaftar.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm. Opsional jika user hanya punya satu farm.'],
            ],
            'required' => [],
        ];
    }

    public function handle(array $args, User $user): array
    {
        $farms = $this->accessibleFarms($user);

        if ($farms->isEmpty()) {
            return ['error' => 'Anda belum tergabung di farm mana pun.'];
        }

        if (isset($args['farm_id'])) {
            if (! is_numeric($args['farm_id']) || (int) $args['farm_id'] < 1) {
                return ['error' => 'Parameter farm_id tidak valid.'];
            }

            $farms = $farms->filter(fn (Farm $farm): bool => $farm->id === (int) $args['farm_id']);

            if ($farms->isEmpty()) {
                return ['error' => 'Farm tidak ditemukan atau Anda tidak memiliki akses.'];
            }
        }

        $customers = Customer::query()
            ->whereIn('farm_id', $farms->pluck('id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ])
            ->all();

        return ['data' => $customers];
    }
}
