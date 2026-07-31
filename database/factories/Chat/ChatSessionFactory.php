<?php

namespace Database\Factories\Chat;

use App\Models\Chat\ChatSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => Str::limit(fake()->sentence(4), 60, ''),
        ];
    }

    public function untitled(): static
    {
        return $this->state(['title' => null]);
    }
}
