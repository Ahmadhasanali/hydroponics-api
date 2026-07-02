<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tank>
 */
class TankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'created_by' => User::factory(),
            'name' => fake()->word(),
            'capacity_liter' => fake()->randomFloat(2, 50, 1000),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
