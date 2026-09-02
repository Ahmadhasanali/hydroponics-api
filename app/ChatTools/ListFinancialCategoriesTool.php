<?php

namespace App\ChatTools;

use App\Models\Farm;
use App\Models\Farm\FinancialCategory;
use App\Models\User;

class ListFinancialCategoriesTool extends BaseTool
{
    public function name(): string
    {
        return 'list_financial_categories';
    }

    public function description(): string
    {
        return 'Mendapatkan daftar kategori transaksi keuangan (id, nama, type income/expense) milik farm pengguna. Gunakan tool ini untuk mengetahui category_id yang valid saat akan mencatat transaksi.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'farm_id' => ['type' => 'INTEGER', 'description' => 'ID farm untuk memfilter kategori. Opsional jika user hanya punya satu farm.'],
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

        $categories = FinancialCategory::query()
            ->where('is_active', true)
            ->where(function ($query) use ($farms): void {
                $query->whereNull('farm_id')->orWhereIn('farm_id', $farms->pluck('id'));
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
            ])
            ->all();

        return ['data' => $categories];
    }
}
