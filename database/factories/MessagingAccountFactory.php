<?php

namespace Database\Factories;

use App\Models\MessagingAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessagingAccountFactory extends Factory
{
    protected $model = MessagingAccount::class;

    public function definition(): array
    {
        return ['channel' => 'telegram', 'external_id' => (string) fake()->numberBetween(100000000, 999999999), 'user_id' => User::factory(), 'default_farm_id' => null, 'linked_at' => now()];
    }
}
