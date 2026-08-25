<?php

namespace Database\Seeders;

use App\Models\Farm\FinancialCategory;
use Illuminate\Database\Seeder;

class FinancialCategorySeeder extends Seeder
{
    private const DEFAULTS = [
        'expense' => [
            'Nutrisi (AB Mix)', 'pH Down', 'Listrik', 'Air', 'Benih/Bibit',
            'Tenaga Kerja', 'Alat & Peralatan', 'Sewa Lahan', 'Lain-lain',
        ],
        'income' => ['Penjualan Panen', 'Lain-lain'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $type => $names) {
            foreach ($names as $name) {
                FinancialCategory::firstOrCreate([
                    'farm_id' => null,
                    'name' => $name,
                    'type' => $type,
                ], [
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }
    }
}
