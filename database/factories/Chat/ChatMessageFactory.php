<?php

namespace Database\Factories\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->sentence(),
        ];
    }

    /**
     * Define a parent relationship for the model.
     *
     * Laravel guesses the relationship name from the related model's class
     * name ("chatSession"), but the model exposes it as "session".
     *
     * @param  Model|self  $factory
     * @param  string|null  $relationship
     * @return static
     */
    public function for($factory, $relationship = null)
    {
        if ($relationship === null && $factory instanceof ChatSession) {
            $relationship = 'session';
        }

        return parent::for($factory, $relationship);
    }
}
