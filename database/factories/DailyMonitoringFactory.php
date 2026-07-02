<?php

namespace Database\Factories;

use App\Models\Farm\DailyMonitoring;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyMonitoringFactory extends Factory
{
    protected $model = DailyMonitoring::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tank_id' => Tank::factory(),
            'log_date' => fake()->date(),
            'ppm' => fake()->randomFloat(2, 500, 1500),
            'ph' => fake()->randomFloat(2, 5.0, 7.0),
            'water_temperature' => fake()->optional()->randomFloat(1, 18, 30),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
