<?php

namespace Database\Factories;

use App\Models\MessagingLinkCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessagingLinkCodeFactory extends Factory
{
    protected $model = MessagingLinkCode::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'channel' => 'telegram', 'code' => strtoupper(fake()->bothify('??##??')), 'expires_at' => now()->addSeconds(60), 'used_at' => null];
    }
}
