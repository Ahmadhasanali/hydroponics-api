<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fcm_token' => fake()->unique()->regexify('[A-Za-z0-9:._-]{120,160}'),
            'platform' => 'android',
            'device_info' => fake()->optional()->userAgent(),
        ];
    }
}
