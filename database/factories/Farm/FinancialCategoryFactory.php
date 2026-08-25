<?php

namespace Database\Factories\Farm;

use App\Models\Farm\FinancialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCategory>
 */
class FinancialCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_id' => null,
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['income', 'expense']),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function forFarm(int $farmId): static
    {
        return $this->state(fn () => ['farm_id' => $farmId]);
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => 'income']);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => 'expense']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
