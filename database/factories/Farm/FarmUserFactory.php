<?php

namespace Database\Factories\Farm;

use App\Models\Farm;
use App\Models\Farm\FarmUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmUser>
 */
class FarmUserFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['owner', 'manager', 'operator']),
        ];
    }
}
