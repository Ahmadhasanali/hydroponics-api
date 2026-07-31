<?php

namespace Database\Factories\Chat;

use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
        ];
    }

    public function untitled(): static
    {
        return $this->state(['title' => null]);
    }
}
