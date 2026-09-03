<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->randomElement(['Selada', 'Pakcoy', 'Kangkung', 'Bayam']),
            'unit' => 'kg',
            'default_price' => fake()->numberBetween(10000, 40000),
            'is_active' => true,
        ];
    }
}
