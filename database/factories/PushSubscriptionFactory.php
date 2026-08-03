<?php

namespace Database\Factories;

use App\Models\Farm\Staff;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'subscribable_type' => User::class,
            'subscribable_id' => User::factory(),
            'fcm_token' => fake()->unique()->regexify('[A-Za-z0-9:._-]{120,160}'),
            'platform' => 'android',
            'device_info' => fake()->optional()->userAgent(),
        ];
    }

    public function forSubscribable(User|Staff $subscribable): static
    {
        return $this->state(fn () => [
            'subscribable_type' => $subscribable::class,
            'subscribable_id' => $subscribable->id,
        ]);
    }
}
