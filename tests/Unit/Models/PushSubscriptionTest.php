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

    public function test_subscription_morphs_to_subscribable(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::factory()->forSubscribable($user)->create();

        $this->assertTrue($subscription->subscribable->is($user));
        $this->assertTrue($user->pushSubscriptions()->first()->is($subscription));
    }
}
