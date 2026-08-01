<?php

namespace Tests\Unit\Models;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_factory_creates_subscription(): void
    {
        $subscription = PushSubscription::factory()->create();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_subscription_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($subscription->user->is($user));
        $this->assertTrue($user->pushSubscriptions()->first()->is($subscription));
    }
}
