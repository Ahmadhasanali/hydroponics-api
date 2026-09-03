<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\Product;
use App\Models\User;

class ListProductsTool extends BaseTool
{
    public function name(): string
    {
        return 'list_products';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar produk (beserta id, satuan kg/pcs, dan harga default) milik farm pengguna. Panggil tool ini sebelum create_sale agar bisa memakai product_id dan harga default yang benar.';
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

        $products = Product::query()
            ->whereIn('farm_id', $farms->pluck('id'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'default_price' => (float) $product->default_price,
            ])
            ->all();

        return ['data' => $products];
    }
}
