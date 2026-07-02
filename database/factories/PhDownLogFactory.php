<?php

namespace Database\Factories;

use App\Models\Farm\PhDownLog;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhDownLogFactory extends Factory
{
    protected $model = PhDownLog::class;

    public function definition(): array
    {
        $phBefore = fake()->randomFloat(2, 6.5, 8.0);

        return [
            'user_id' => User::factory(),
            'tank_id' => Tank::factory(),
            'log_date' => fake()->date(),
            'ph_before' => $phBefore,
            'ph_after' => fake()->randomFloat(2, 5.0, $phBefore - 0.1),
            'ph_down_ml' => fake()->randomFloat(2, 1, 50),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
