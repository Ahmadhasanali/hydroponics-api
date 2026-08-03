<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'created_by_type' => User::class,
            'created_by_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'starts_at' => now()->addDay()->setTime(8, 0),
            'recurrence' => null,
            'advance_notify_minutes' => null,
            'is_active' => true,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn () => [
            'recurrence' => ['type' => 'weekly', 'days_of_week' => ['mon', 'wed']],
        ]);
    }
}
