<?php

namespace Database\Factories;

use App\Models\Farm\NutrientAddition;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NutrientAdditionFactory extends Factory
{
    protected $model = NutrientAddition::class;

    public function definition(): array
    {
        $ppmBefore = fake()->randomFloat(2, 500, 1000);

        return [
            'user_id' => User::factory(),
            'tank_id' => Tank::factory(),
            'log_date' => fake()->date(),
            'ppm_before' => $ppmBefore,
            'ppm_after' => fake()->randomFloat(2, $ppmBefore + 50, 1500),
            'nutrient_a_ml' => fake()->randomFloat(2, 10, 500),
            'nutrient_b_ml' => fake()->randomFloat(2, 10, 500),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
