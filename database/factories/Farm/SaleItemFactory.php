<?php

namespace Database\Factories\Farm;

use App\Models\Farm\Product;
use App\Models\Farm\Sale;
use App\Models\Farm\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 5);
        $price = fake()->numberBetween(10000, 40000);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_name' => 'Selada',
            'unit' => 'kg',
            'qty' => $qty,
            'price' => $price,
            'subtotal' => round($qty * $price, 2),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SaleItem $item): void {
            $item->subtotal = round((float) $item->qty * (float) $item->price, 2);
        });
    }
}
